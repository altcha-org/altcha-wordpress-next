/**
 * Copyright (c) 2025 BAU Software s.r.o., Czechia. All rights reserved.
 *
 * This file is part of the Software licensed under the
 * END-USER LICENSE AGREEMENT (EULA)
 *
 * License Summary:
 * - Source is available for review, testing, debugging, and evaluation.
 * - Distribution of the Software or source code is prohibited.
 * - Modifications are allowed only for internal testing/debugging,
 *   not for production or deployment.
 *
 * The full license text can be found in the LICENSE file
 * distributed with this source code.
 *
 * Unauthorized distribution, modification, or production use of
 * this Software is strictly prohibited.
 */

(() => {
  const debug =
    /^localhost|.*\.localhost|.*\.test$/.test(location.hostname) ||
    /:\d+$/.test(location.host) ||
    localStorage.getItem("altcha_debug") === "1";
  const defaultActionName = "action";
  const config = pluginData?.altcha || {};
  const actions = config.actions || [];
  const paths = config.paths || [];
  const cookiePath = config.cookiePath || "/";
  const sitePath = config.sitePath || "/";
  const protectLogin = !!config.protectLogin;
  const protectRegistration = !!config.protectRegistration;
  const protectPasswordReset = !!config.protectPasswordReset;
  const protectComments = !!config.protectComments;
  const plugins = {
    coblocks: {
      actions: ["coblocks-form-submit"],
      formIdField: "form-submit",
    },
    "contact-form-7": {
      formIdField: "_wpcf7",
      paths: ["/contact-form-7/", "#wpcf7-"],
    },
    divi: {
      actions: ["et_pb_contact_form_submit"],
      formIdField: "et_pb_contact_form_id"
    },
    "elementor-pro": {
      actions: ["elementor_pro_forms_send_form"],
      formIdField: "form_id"
    },
    eventprime: {
      actions: ["ep_save_event_booking"],
    },
    "everest-forms": {
      fields: ["everest_forms[id]"],
      formIdField: "everest_forms[id]"
    },
    formidable: {
      fields: ["frm_action"],
      formIdField: "form_id",
    },
    forminator: {
      fields: ["forminator_nonce"],
      formIdField: "form_id",
    },
    fluentform: {
      actions: ["fluentform_submit"],
      fields: ["__fluent_form_embded_post_id"],
      formIdField: "form_id",
    },
    gravityforms: {
      fields: ["gform_submission_method"],
      formIdField: "gform_submit",
    },
    "kali-forms": {
      actions: ["kaliforms_form_process"],
      formIdField: "data[formId]",
    },
    "html-forms": {
      fields: ["_hf_form_id"],
      formIdField: "_hf_form_id",
    },
    mailpoet: {
      actions: ["mailpoet"],
      formIdField: "data[form_id]"
    },
    metform: {
      paths: ["/metform/"],
      formIdField: (request) => request?.url?.pathname.match(/(\d+)$/)?.[1],
    },
    newsletter: {
      actions: ["tnp"],
    },
    noptin: {
      paths: ["/noptin/"],
      formIdField: "noptin_form_id",
    },
    ninjaforms: {
      actions: ["nf_ajax_submit"],
      formIdField: (request) =>
        request?.body?.formData.match(/"id":"(\d+)"/)?.[1],
    },
    sureforms: {
      paths: ["/sureforms/v1/submit-form"],
      formIdField: "form-id",
    },
    "user-registration": {
      fields: [
        "user-registration-login-nonce",
        "ur_reset_password",
        "ur_frontend_form_nonce"
      ],
    },
    woocommerce: {
      fields: [
        "wc_reset_password",
        "woocommerce-login-nonce"
      ],
      paths: ["/wc/store/v1/", "/wc-api/"],
    },
    "wp-members": {
      fields: ["_wpmem_login_nonce", "_wpmem_register_nonce"],
    },
    wpdiscuz: {
      fields: ["wpdiscuz_unique_id"],
    },
    wpforms: {
      actions: ["wpforms_submit"],
      fields: ["wpforms[id]"],
      formIdField: "wpforms[id]",
    },
    "wp": {
      match: (request) => {
        if (request.url.pathname.endsWith("/wp-login.php")) {
          const action = matchActions(request, [
            "lostpassword",
            "register",
          ]);
          return action || "login";
        } else if (request.url.pathname.endsWith("/wp-register.php")) {
          return "register";
        } else if (request.url.pathname.endsWith("/wp-comments-post.php")) {
          return "comments";
        }
      },
    },
  };
  let interceptor;
  let refreshUnderAttackPromise = null;

  function createRegExp(pattern) {
    return new RegExp(
      "^" +
        pattern.replace(/[.+?^${}()|[\]\\]/g, "\\$&").replace(/\*/g, ".*") +
        "$",
      "i"
    );
  }

  function detectPlugin(request, action) {
    for (const plugin in plugins) {
      const opts = plugins[plugin];
      if (opts.match) {
        const pluginAction = opts.match(request, action);
        if (pluginAction) {
          return {
            pluginAction,
            plugin,
          };
        }
      }
      if (opts.fields && opts.fields.some((field) => request.body?.[field] !== undefined)) {
        return {
          pluginAction: null,
          plugin,
        };
      }
      if (opts.actions?.length) {
        const pluginAction = matchActions(request, opts.actions);
        if (pluginAction) {
          return {
            pluginAction,
            plugin,
          };
        }
      }
      if (
        opts.paths?.length &&
        opts.paths.some((path) => request.url.href.includes(path))
      ) {
        return {
          pluginAction: null,
          plugin,
        };
      }
    }
    return {
      pluginAction: null,
      plugin: null,
    };
  }

  function getTimeZone() {
    try {
      return Intl.DateTimeFormat().resolvedOptions().timeZone;
    } catch {
      // noop
    }
  }

  function getCookie(name) {
    const nameEQ = name + "=";
    const cookies = document.cookie.split(";");
    for (let i = 0; i < cookies.length; i++) {
      let c = cookies[i];
      while (c.charAt(0) === " ") {
        c = c.substring(1, c.length);
      }
      if (c.indexOf(nameEQ) === 0) {
        return decodeURIComponent(c.substring(nameEQ.length, c.length));
      }
    }
    return null;
  }

  function setCookie(name, value, props = "") {
    document.cookie = `${encodeURIComponent(name)}=${encodeURIComponent(
      value
    )}; SameSite=strict; ${props}`.trim();
  }

  function matchActions(request, patterns) {
    let matched = false;
    let rx = null;
    for (let pattern of patterns) {
      const isNegation = pattern.startsWith("!");
      const cleanPattern = isNegation ? pattern.slice(1) : pattern;
      let name = defaultActionName;
      let value = null;
      if (cleanPattern.includes("=")) {
        const parts = cleanPattern.split("=");
        name = parts[0];
        rx = createRegExp(parts[1]);
      } else if (cleanPattern === "*") {
        matched = String(
          request.body?.[name] || request.url.searchParams.get(name) || ""
        );
        continue;
      } else {
        rx = createRegExp(cleanPattern);
      }
      value = String(
        request.body?.[name] || request.url.searchParams.get(name) || ""
      );
      if (rx) {
        if ((name === defaultActionName || !!value) && rx.test(value)) {
          if (isNegation) {
            return false;
          }
          if (cleanPattern.includes('=')) {
            return `${name}=`;
          }
          return name === defaultActionName ? value : `${name}=`;
        }
      }
    }
    return matched;
  }

  function matchPattern(str, patterns) {
    let matched = false;
    for (let pattern of patterns) {
      const isNegation = pattern.startsWith("!");
      const cleanPattern = isNegation ? pattern.slice(1) : pattern;
      if (cleanPattern === "*") {
        matched = true;
      } else if (createRegExp(cleanPattern).test(str)) {
        if (isNegation) {
          return false;
        }
        return true;
      }
    }
    return matched;
  }

  function normalizePath(path) {
    if (sitePath !== "/" && path.startsWith(sitePath)) {
      path = "/" + path.slice(sitePath.length).replace(/^\/+/, "");
    }
    return path.replace(/^\/index.php\//, "/");
  }

  async function refreshUnderAttackCookie() {
    const underAttackCookie = getCookie("altcha_under_attack");
    const expires = parseInt(
      getCookie("altcha_under_attack_expires") || "0",
      10
    );
    const ttl = parseInt(getCookie("altcha_under_attack_ttl") || "0", 10);
    if (
      !refreshUnderAttackPromise &&
      (!underAttackCookie || expires < Date.now() + 15_000)
    ) {
      refreshUnderAttackPromise = interceptor.requestAltchaVerification(
        {
          "params.under_attack": "1",
        },
        config.underAttackChallengeUrl || config.widget.challenge
      );
      try {
        const payload = await refreshUnderAttackPromise;
        if (payload) {
          setCookie(
            "altcha_under_attack",
            payload,
            "path=" +
              cookiePath +
              "; expires=" +
              new Date(Date.now() + (ttl || 30_000)).toUTCString()
          );
        }
      } finally {
        refreshUnderAttackPromise = null;
      }
    }
  }

  interceptor = altchaInterceptor({
    filter: async (request) => {
      const isLogin = request.method === "POST" && !!request.body?.log && !!request.body?.pwd;
      const isRegistration = request.method === "POST" && (request.url.pathname.endsWith("/wp-register.php") || request.url.searchParams.get("action") === "register");
      const isPasswordReset = request.method === "POST" && request.url.searchParams.get("action") === "lostpassword";
      const isComment = request.method === "POST" && !!request.url.pathname.endsWith("/wp-comments-post.php");
      if (request.url.origin !== location.origin) {
        return false;
      }
      if (getCookie("altcha")) {
        return false;
      }
      if (request.body?.altcha) {
        return false;
      }
      if (
        request.method !== "POST" &&
        !request.url.pathname.endsWith("admin-ajax.php")
      ) {
        return false;
      }
      if (isLogin && !protectLogin) {
        return false;
      } else if (isRegistration && !protectRegistration) {
        return false;
      } else if (isPasswordReset && !protectPasswordReset) {
        return false;
      } else if (isComment && !protectComments) {
        return false;
      }
      const pathMatched = matchPattern(normalizePath(request.url.pathname), paths);
      const action = matchActions(request, actions);
      if (!isLogin && !isRegistration && !isPasswordReset && !isComment && action === false && !pathMatched) {
        return false;
      }
      const { plugin, pluginAction } = detectPlugin(request, action);
      if (config.underAttack) {
        await refreshUnderAttackCookie();
      }
      const formIdField = plugins[plugin]?.formIdField;
      return {
        action: typeof action === "string" ? action : (pluginAction || null),
        form_id:
          (formIdField && typeof formIdField === "function"
            ? formIdField(request)
            : request.body?.[formIdField]) || null,
        interceptor: "1",
        plugin,
        tz: getTimeZone(),
      };
    },
    onVerified: (payload) => {
      setCookie("altcha", payload, "path=" + cookiePath);
    },
    debug,
    ...config,
    widget: {
      debug,
      ...config.widget,
    },
  });

  if (config.widget.powAlgorithm === "ARGON2ID") {
    try {
      $altcha.algorithms.set("ARGON2ID", () => new Worker(config.workerArgon2idPath));
    } catch (err) {
      console.error(err);
      console.error('ALTCHA failed to register ARGON2ID worker.');
    }
  }

  if (!window.isSecureContext) {
    console.warn(
      "Secure Context (HTTPS) Required! ALTCHA requires a secure connection (HTTPS), also known as a Secure Context [https://developer.mozilla.org/en-US/docs/Web/Security/Secure_Contexts]. Please access this page over HTTPS or use whitelisted localhost domains for development."
    );
  }
})();
