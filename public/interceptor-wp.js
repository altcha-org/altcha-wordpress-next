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
  const plugins = {
    coblocks: {
      actions: ["coblocks-form-submit"],
      formIdField: "form-submit",
    },
    "contact-form-7": {
      formIdField: "_wpcf7",
      paths: ["/contact-form-7/", "#wpcf7-"],
    },
    "elementor-pro": {
      actions: ["elementor_pro_forms_send_form"],
    },
    formidable: {
      field: "frm_action",
      formIdField: "form_id",
    },
    forminator: {
      field: "forminator_nonce",
      formIdField: "form_id",
    },
    fluentform: {
      actions: ["fluentform_submit"],
      field: "__fluent_form_embded_post_id",
      formIdField: "form_id",
    },
    gravityforms: {
      field: "gform_submission_method",
      formIdField: "gform_submit",
    },
    "html-forms": {
      field: "_hf_form_id",
      formIdField: "_hf_form_id",
    },
    metform: {
      paths: ["/metform/"],
      formIdField: (request) => request?.url?.pathname.match(/(\d+)$/)?.[1],
    },
    ninjaforms: {
      actions: ["nf_ajax_submit"],
      formIdField: (request) =>
        request?.body?.formData.match(/"id":"(\d+)"/)?.[1],
    },
    woocommerce: {
      paths: ["/wc/store/v1/"],
    },
    "wp-comments": {
      paths: ["/wp-comments-post.php"],
    },
    "wp-login": {
      paths: ["/wp-login.php"],
    },
    wpdiscuz: {
      field: "wpdiscuz_unique_id",
    },
    wpforms: {
      actions: ["wpforms_submit"],
      field: "wpforms[id]",
      formIdField: "wpforms[id]",
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
      if (opts.field && request.body?.[opts.field] !== undefined) {
        return plugin;
      }
      if (opts.actions?.length && opts.actions.some((a) => a === action)) {
        return plugin;
      }
      if (
        opts.paths?.length &&
        opts.paths.some((path) => request.url.href.includes(path))
      ) {
        return plugin;
      }
    }
    return null;
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
          return name === defaultActionName ? value : `${name}=${value}`;
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
        config.underAttackChallengeUrl || config.widget.challengeurl
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
      if (!matchPattern(normalizePath(request.url.pathname), paths)) {
        return false;
      }
      const action = matchActions(request, actions);
      if (action === false) {
        return false;
      }
      const plugin = detectPlugin(request, action);
      if (config.underAttack) {
        await refreshUnderAttackCookie();
      }
      const formIdField = plugins[plugin]?.formIdField;
      return {
        action: typeof action === "string" ? action : null,
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
    invisible: false,
    debug,
    ...config,
    widget: {
      debug,
      ...config.widget,
    },
  });

  if (!window.isSecureContext) {
    console.warn(
      "Secure Context (HTTPS) Required! ALTCHA requires a secure connection (HTTPS), also known as a Secure Context [https://developer.mozilla.org/en-US/docs/Web/Security/Secure_Contexts]. Please access this page over HTTPS or use whitelisted localhost domains for development."
    );
  }
})();
