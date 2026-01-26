=== ALTCHA: Spam Protection ===
Tags: altcha, captcha, recaptcha, hcaptcha, spam, anti-spam, anti-bot
Author: Altcha.org
Author URI: https://altcha.org
Version: 2.5.0
Stable tag: 2.5.0
Requires at least: 5.0
Requires PHP: 7.3
Tested up to: 6.9
License: END-USER LICENSE AGREEMENT (EULA)
License URI: https://altcha.org/docs/v2/wordpress/eula

ALTCHA for WordPress v2 delivers professional, invisible spam protection that works with any form plugin, handles heavy traffic, and keeps your site safe without annoying visitors. With built-in firewall, rate limiting, and GDPR-compliant security, it’s the all-in-one solution for fast, reliable, and privacy-first WordPress protection.

== Description ==

**ALTCHA for WordPress v2** is the professional solution for keeping your website safe from spam, bots, and abuse — without frustrating your visitors. Unlike traditional CAPTCHAs that interrupt the user experience, ALTCHA runs silently in the background, delivering **invisible, privacy-first protection** for all your forms.

Whether you’re running a blog, an online store, or a high-traffic business site, ALTCHA makes sure your site stays fast, secure, and compliant.

## Why ALTCHA?

* **Blocks 99% of spam & abuse** before it ever reaches your site
* **Invisible protection** — no puzzles, no images, no interruptions
* **Works everywhere** — integrates with *any* form plugin using the Request Interceptor
* **Handles heavy traffic** — stay online under stress with *Under Attack Mode*
* **Firewall & rate limiting included** for abuse prevention at scale
* **Privacy-first & GDPR-compliant** — built with accessibility and compliance in mind
* **Unlimited protection** — no external services, no verification limits

[Learn more](https://altcha.org/docs/v2/wordpress).

## Perfect for Professionals

ALTCHA v2 is designed for serious WordPress site owners who need **reliable, production-grade security**. It’s a complete protection layer that scales with your website, backed by professional support when you need it.

## Get Started

To get started, see the [documentation](https://altcha.org/docs/v2/wordpress).
 
== Installation ==
 
Download and install the plugin manually:

1. Download the `.zip` from the [Releases](https://github.com/altcha-org/altcha-wordpress-next/releases).
2. Upload `altcha` folder to the `/wp-content/plugins/` directory  
3. Activate the plugin through the 'Plugins' menu in WordPress  
4. Review the settings

== Changelog ==

= 2.5.0 =
* Added option to configure challenge expiration
* Added filter `altcha_get_settings`

= 2.4.2 =
* Fixed handling of malformatted altcha payload

= 2.4.1 =
* Fixed wp_scripts initialization error

= 2.4.0 =
* Added TranslatePress and PixelYourSite default actions

= 2.3.1 =
* Fixed possible replay attacks via salt splicing.

= 2.3.0 =
* Removed enforcement of default actions/paths during other plugins activation to avoid overwriting user configuration
* Fixed the enqueue order of the obfuscation script
* Added missing legacy and less commonly used timezones for geo-detection

= 2.2.0 =
* Introduced advanced event filtering for logs.
* Added a new "Bot" event type to differentiate between bot and failed attempts.
* Added request body logging for failed or bot attempts (can be enabled in Analytics settings).
* Added "Trusted Proxies" settings to improve security with IP detection from the HTTP_X_FORWARDED_FOR header.

= 2.1.2 =
* Add EventPrime plugin defaults
* Add "Tested up to" with WP 6.9
* Fix minor UI issues

= 2.1.1 =
* Add WooCommerce default exclusion path !/wc-api/*
* Fix SAPI CLI bypass

= 2.1.1 =
* Add WooCommerce default exclusion path !/wc-api/*
* Fix SAPI CLI bypass

= 2.1.0 =
* Multi-site support
* Obfuscation shortcode
* Ability to hide ALTCHA menu item from the sidebar
* Add meta tags for Git-Updater

= 2.0.11 =
* Fix analytics timezone mismatch issues
* Improve events table pagination

= 2.0.10 =
* Add altcha_inject filter
* Fix auto-updater issues

= 2.0.9 =
* Fix: Add missing timezones for geo-location
* UI improvements

= 2.0.8 =
* Fix MainWP compatibility
* Add bypass cookies

= 2.0.7 =
* Fix translation domain notice

= 2.0.6 =
* Fix Wordfence login issues

= 2.0.5 =
* Add missing timezones for geo-location
* UI improvements and fixes

= 2.0.4 =
* Auto-apply recommended actions and paths when plugins are activated
* Login protection is enabled by default
* Fix login protection with paths without wildcard
* UI improvements and fixes

= 2.0.3 =
* UI improvements and fixes

= 2.0.2 =
* Under Attack Mode is now disabled on excluded actions and paths
* Default excluded paths for "Real Cookie Banner"
* Enable debugging mode using local storage variable

= 2.0.1 =
* Fix login issues related to "hide login" plugins

= 2.0.0 =
* First public release
