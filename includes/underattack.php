<?php

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

if (! defined("ABSPATH")) exit;

$plugin = AltchaPlugin::$instance;
$version = AltchaPlugin::$version;
$widget_script_cdn_url = AltchaPlugin::$widget_cdn_url;
$widget_script_filename = "altcha.min.js";
$widget_script_url = plugin_dir_url(dirname(__FILE__)) . "public/$widget_script_filename?ver={$version}";
$under_attack = $plugin->get_under_attack();
$hide_logo = $plugin->get_settings("hideLogo", false);
$hide_footer = $plugin->get_settings("hideFooter", false);
$cookie_ttl_ms = $plugin->get_under_attack_cookie_expire();
$cookie_name = "altcha_under_attack";
$cookie_path = COOKIEPATH;

$challenge = $plugin->generate_challenge(null, (int) $under_attack, floor($cookie_ttl_ms / 1000), array(
  "ip" => $plugin->get_ip_address_hash(),
));

header("Cache-Control: no-cache, no-store, max-age=0");
header("Pragma: no-cache");
header("X-Robots-Tag: noindex, nofollow");
header("X-Frame-Options: SAMEORIGIN");

if (AltchaPlugin::$is_ajax) {
  status_header(403);
  header("Content-Type: application/json");
  echo json_encode(array(
    "altcha_under_attack" => true,
    "success" => false,
  ));
  exit;
}

$widget_attrs = $plugin->get_widget_attrs(array(
  "auto" => "onload",
  "challengejson" => json_encode($challenge),
  "challengeurl" => "",
  "customfetch" => "",
  "delay" => 2000,
  "hidefooter" => true,
  "hidelogo" => $hide_logo
));
$widget_html = $plugin->get_widget_html($widget_attrs);
$footer = $hide_footer ? "" : "<footer>
  <div>
    Protected by <a href=\"https://altcha.org\" taget=\"_blank\">ALTCHA</a><sup>&reg;</sup>
      — Next-Gen Captcha and Spam Protection.
  </div>
</footer>";

header("Content-Type: text/html");
echo "<html>
  <head>
    <title>Verification required!</title>
    <meta name=\"viewport\" content=\"width=device-width, initial-scale=1\">
    <style>
      body {
        background-color: #efefef;
        font-family: sans-serif;
        font-size: 16px;
      }
      main {
        --altcha-color-base: white;
        background-color: #fff;
        border: 1px solid #ddd;
        border-radius: 5px;
        display: flex;
        flex-direction: column;
        gap: 1.5rem;
        max-width: 720px;
        margin: 3rem auto 0 auto;
        padding: 1rem;
      }
      footer {
        font-size: 80%;
        max-width: 720px;
        margin: 2rem auto;
        text-align: center;
      }
      p {
        margin: 0;
      }
      a {
        color: inherit;
        text-decoration: underline;
      }
      .altcha-no-javascript {
        color: red;
      }
      #hostname-wrap {
        border-bottom: 1px solid #ddd;
        display: flex;
        gap: 0.5rem;
        align-items: center;
        padding: 0 0 0.5rem 0;
      }
      #hostname-wrap svg {
        color: green;
        height: 1.5rem;
        width: 1.5rem;
      }
      #hostname {
        font-size: 130%;
        font-weight: 400;
        white-space: nowrap;
        text-overflow: ellipsis;
        overflow: hidden;
        max-width: 100%;
      }
      #unsecure-context {
        color: red;
        display: none;
        flex-direction: column;
        gap: 0.75rem;
      }
      #widget {
        min-height: 50px;
      }
      #loading {
        align-items: center;
        border: 1px solid #bbb;
        border-radius: 3px;
        box-sizing: border-box;
        display: flex;
        font-size: 90%;
        opacity: 0.4;
        max-width: 260px;
        min-height: 50px;
        padding: 0 1rem;
      }
      #text {
        display: flex;
        flex-direction: column;
        gap: 0.75rem;
      }
      #info {
        font-size: 120%;
        line-height: 135%;
      }
      #help {
        font-size: 90%;
        line-height: 135%;
        opacity: 0.7;
      }
      #help p + p {
        margin-top: 1rem;
      }
      @media screen and (max-width: 768px) {
        body {
          font-size: 14px;
        }
        main {
          margin: 0;
        }
      }
    </style>
  </head>
  <body>
    <main id=\"altcha-under-attack\">
      <div id=\"hostname-wrap\">
        <div>
          <svg xmlns=\"http://www.w3.org/2000/svg\" viewBox=\"0 0 24 24\" fill=\"currentColor\"><path d=\"M3.78307 2.82598L12 1L20.2169 2.82598C20.6745 2.92766 21 3.33347 21 3.80217V13.7889C21 15.795 19.9974 17.6684 18.3282 18.7812L12 23L5.6718 18.7812C4.00261 17.6684 3 15.795 3 13.7889V3.80217C3 3.33347 3.32553 2.92766 3.78307 2.82598ZM5 4.60434V13.7889C5 15.1263 5.6684 16.3752 6.7812 17.1171L12 20.5963L17.2188 17.1171C18.3316 16.3752 19 15.1263 19 13.7889V4.60434L12 3.04879L5 4.60434ZM13 10H16L11 17V12H8L13 5V10Z\"></path></svg>
        </div>
        <div id=\"hostname\"></div>
      </div>

      <div id=\"unsecure-context\">
        <p><b>Secure Context (HTTPS) Required</b></p>
        <p>This page requires a secure connection (HTTPS), also known as a <a href=\"https://developer.mozilla.org/en-US/docs/Web/Security/Secure_Contexts\" target=\"_blank\" class=\"link\">Secure Context</a>. Please access this page over HTTPS or use whitelisted localhost domains for development.</p>
      </div>

      <div id=\"widget\">
        <div id=\"loading\">Loading...</div>
        {$widget_html}
      </div>

      <div id=\"text\">
        <div id=\"info\"></div>
        <div id=\"help\"></div>
      </div>
    </main>

    {$footer}

    <script>
      const translations = {
        de: {
          title: 'Überprüfung erforderlich!',
          loading: 'Wird geladen...',
          help: '<p>Diese schnelle Sicherheitsprüfung schützt unsere Website vor Spam, Bots und Missbrauch und macht sie für alle sicherer.</p><p>Probleme? Setzen Sie einfach das Häkchen oben, um zu bestätigen, dass Sie kein Roboter sind. Sobald die Überprüfung abgeschlossen ist, werden Sie weitergeleitet. Wenn die Probleme weiterhin bestehen, versuchen Sie die Seite zu aktualisieren oder Ihre Internetverbindung zu überprüfen.</p>',
          info: '<p>Bitte warten Sie einen Moment, während wir Ihre Anfrage überprüfen…</p>',
        },
        en: {
          title: 'Verification Required!',
          loading: 'Loading...',
          help: '<p>This quick security check helps protect our website against spam, bots, and abuse, making it safer for everyone.</p><p>Having trouble? Just check the box above to confirm you’re not a robot. Once verified, you’ll be redirected. If issues persist, try refreshing the page or checking your internet connection.</p>',
          info: '<p>Please wait a moment while we verify your request…</p>',
        },
        es: {
          title: '¡Verificación requerida!',
          loading: 'Cargando...',
          help: '<p>Esta rápida comprobación de seguridad ayuda a proteger nuestro sitio web contra el spam, los bots y el abuso, haciéndolo más seguro para todos.</p><p>¿Tienes problemas? Marca la casilla de arriba para confirmar que no eres un robot. Una vez verificado, serás redirigido. Si los problemas persisten, intenta actualizar la página o comprobar tu conexión a internet.</p>',
          info: '<p>Espera un momento mientras verificamos tu solicitud…</p>',
        },
        fr: {
          title: 'Vérification requise !',
          loading: 'Chargement...',
          help: '<p>Ce contrôle de sécurité rapide aide à protéger notre site web contre le spam, les robots et les abus, le rendant plus sûr pour tout le monde.</p><p>Des difficultés ? Cochez simplement la case ci-dessus pour confirmer que vous n’êtes pas un robot. Une fois vérifié, vous serez redirigé. Si le problème persiste, essayez de rafraîchir la page ou de vérifier votre connexion internet.</p>',
          info: '<p>Veuillez patienter un instant pendant que nous vérifions votre demande…</p>',
        },
        it: {
          title: 'Verifica necessaria!',
          loading: 'Caricamento...',
          help: '<p>Questo rapido controllo di sicurezza aiuta a proteggere il nostro sito web da spam, bot e abusi, rendendolo più sicuro per tutti.</p><p>Problemi? Basta selezionare la casella qui sopra per confermare che non sei un robot. Una volta verificato, verrai reindirizzato. Se i problemi persistono, prova ad aggiornare la pagina o a controllare la tua connessione internet.</p>',
          info: '<p>Attendi un momento mentre verifichiamo la tua richiesta…</p>',
        },
        ja: {
          title: '確認が必要です！',
          loading: '読み込み中...',
          help: '<p>この簡単なセキュリティチェックは、スパムやボット、不正利用から当サイトを守り、すべての利用者にとって安全にするためのものです。</p><p>問題が発生しましたか？ 上のチェックボックスを選択して、ロボットではないことを確認してください。確認が完了すると、自動的にリダイレクトされます。問題が続く場合は、ページを再読み込みするか、インターネット接続を確認してください。</p>',
          info: '<p>リクエストを確認しています。少々お待ちください…</p>'
        },
        pt: {
          title: 'Verificação necessária!',
          loading: 'Carregando...',
          help: '<p>Esta verificação rápida de segurança ajuda a proteger o nosso site contra spam, bots e abusos, tornando-o mais seguro para todos.</p><p>Com problemas? Basta marcar a caixa acima para confirmar que você não é um robô. Depois de verificado, será redirecionado. Se os problemas persistirem, tente atualizar a página ou verificar a sua ligação à internet.</p>',
          info: '<p>Aguarde um momento enquanto verificamos o seu pedido…</p>',
        },
        ru: {
          title: 'Требуется проверка!',
          loading: 'Загрузка...',
          help: '<p>Эта быстрая проверка безопасности помогает защитить наш сайт от спама, ботов и злоупотреблений, делая его безопаснее для всех.</p><p>Возникли проблемы? Просто установите галочку выше, чтобы подтвердить, что вы не робот. После проверки вы будете перенаправлены. Если проблемы сохраняются, попробуйте обновить страницу или проверить подключение к интернету.</p>',
          info: '<p>Пожалуйста, подождите немного, пока мы проверяем ваш запрос…</p>',
        },
        zh: {
          title: '需要验证！',
          loading: '加载中...',
          help: '<p>这个快速的安全检查有助于保护我们的网站免受垃圾信息、机器人和滥用的影响，使其对所有人更安全。</p><p>遇到问题了吗？只需勾选上面的复选框以确认您不是机器人。验证完成后，您将被重定向。如果问题仍然存在，请尝试刷新页面或检查您的网络连接。</p>',
          info: '<p>我们正在验证您的请求，请稍候…</p>'
        }
      };

      window.onerror = (_message, source) => {
        if (source.includes('{$widget_script_filename}')) {
          loadFromCDN();
        }
      };

      const fallbackTimeout = setTimeout(() => {
        loadFromCDN(); 
      }, 10_000);

      function getTimeZone() {
        try {
            return Intl.DateTimeFormat().resolvedOptions().timeZone;
        } catch {
          return 'UTC';
        }
      }

      function injectScript(url, type, cb) {
        const script = document.createElement('script');
        if (type) {
          script.type = type;
        }
        script.src = url;
        script.addEventListener('load', () => {
          setTimeout(() => cb?.(), 0);
        });
        document.body.append(script);
      }

      function loadFromCDN() {
        if (fallbackTimeout) {
          clearTimeout(fallbackTimeout);
        }
        document.getElementById('widget-script')?.remove();
        injectScript('{$widget_script_cdn_url}', 'module', init);
      }

      function setCookie(name, value, props) {
        document.cookie = (encodeURIComponent(name) + '=' + encodeURIComponent(value) + '; path={$cookie_path}; SameSite=strict; ' + (props || '')).trim(); 
      }

      function translate(lang) {
        if (!lang) {
          lang = navigator.language?.split('-')?.[0];
        }
        document.documentElement.lang = lang;
        const tr = translations[lang] || translations.en;
        document.getElementById('loading').innerHTML = tr.loading;
        document.getElementById('help').innerHTML = tr.help;
        document.getElementById('info').innerHTML = tr.info;
        document.title = tr.title;
      }

      function init() {
        if (typeof altchaCreateWorker === 'undefined') {
          // widget did not load
          return false;
        }
        if (fallbackTimeout) {
          clearTimeout(fallbackTimeout);
        }
        const altcha = document.querySelector('altcha-widget');
        if (altcha) {
          document.getElementById('loading')?.remove();
          altcha.addEventListener('verified', (ev) => {
            const payload = ev.detail.payload;
            if (payload) {
              const expiresTime = Date.now() + {$cookie_ttl_ms};
              const props = 'expires=' + new Date(expiresTime).toUTCString() + ';';
              const tz = getTimeZone();
              setCookie('{$cookie_name}', payload, props);
              setCookie('{$cookie_name}_expires', String(expiresTime), props);
              setCookie('{$cookie_name}_ttl', String({$cookie_ttl_ms})); // session cookie
              if (tz) {
                setCookie('{$cookie_name}_tz', tz); // session cookie
              }
              if (location.hostname) {
                setTimeout(() => {
                  location.reload();
                }, 1000);
              }
            }
          })
        }
        return true;
      }

      document.getElementById('hostname').innerText = location.hostname || '';

      if (window.isSecureContext) {
        injectScript('{$widget_script_url}', null, init);
        translate();

      } else {
        document.getElementById('unsecure-context').style.display = 'flex';
        document.getElementById('widget').remove();
      }

    </script>
  </body>
</html>";