<?php

/*
 * Plugin Name: ALTCHA: Spam Protection
 * Text Domain: altcha
 * Description: ALTCHA for WordPress delivers professional, invisible spam protection that works with any form plugin, handles heavy traffic, and keeps your site safe without annoying visitors. With built-in firewall, rate limiting, and GDPR-compliant security, it’s the all-in-one solution for fast, reliable, and privacy-first WordPress protection.
 * Author: Altcha.org
 * Author URI: https://altcha.org
 * GitHub Plugin URI: https://github.com/altcha-org/altcha-wordpress-next
 * Primary Branch: main
 * Release Asset: true
 * Version: 2.5.0
 * Stable tag: 2.5.0
 * Requires at least: 5.0
 * Requires PHP: 7.3
 * Tested up to: 6.9
 * License: END-USER LICENSE AGREEMENT (EULA)
 * License URI: https://altcha.org/docs/v2/wordpress/eula
 *
 *
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

define("ALTCHA_PLUGIN_VERSION", "2.5.0");

include_once(ABSPATH . "wp-admin/includes/plugin.php");

require plugin_dir_path(__FILE__) . "includes/plugin.php";
require plugin_dir_path(__FILE__) . "includes/updater.php";
require plugin_dir_path(__FILE__) . "includes/admin/actions.php";
require plugin_dir_path(__FILE__) . "includes/api.php";
require plugin_dir_path(__FILE__) . "includes/interceptor.php";
require plugin_dir_path(__FILE__) . "includes/integrations/elementor.php";
require plugin_dir_path(__FILE__) . "includes/integrations/formidable.php";
require plugin_dir_path(__FILE__) . "includes/integrations/gravityforms.php";
require plugin_dir_path(__FILE__) . "includes/integrations/html-forms.php";
require plugin_dir_path(__FILE__) . "includes/obfuscation/obfuscation.php";
require plugin_dir_path(__FILE__) . "includes/obfuscation/shortcode.php";

register_activation_hook(__FILE__, "altcha_activate");
register_deactivation_hook(__FILE__, "altcha_deactivate");

$plugin_file = plugin_basename(__FILE__);

add_action("plugins_loaded", "altcha_plugin_loaded");
add_filter("plugin_row_meta", "altcha_details_link", 10, 2);
add_action("altcha_delete_expired_events", "altcha_delete_expired_events_callback");
add_action("admin_menu", "altcha_admin_menu");
add_action("wp_enqueue_scripts", "altcha_enqueue_interceptor_scripts");
add_action("login_enqueue_scripts", "altcha_enqueue_interceptor_scripts");
add_action("wp_dashboard_setup", "altcha_add_dashboard_widget");
add_action("wpmu_new_blog", "altcha_new_site_handler", 10, 6);

add_action("in_plugin_update_message-" . $plugin_file, "altcha_plugin_update_warning", 10, 2);
add_filter("plugin_action_links_" . $plugin_file, "altcha_plugin_settings_link");

add_shortcode("altcha", "altcha_shortcode");

/**
 * Activation hook
 */
function altcha_activate()
{
  altcha_run_migration();
}

/**
 * Deactivation hook
 */
function altcha_deactivate()
{
  $plugin = AltchaPlugin::$instance;
  if (is_multisite()) {
    $sites = get_sites();
    foreach ($sites as $site) {
      switch_to_blog($site->blog_id);
      $plugin->delete_cron_jobs();
      restore_current_blog();
    }
  } else {
    $plugin->delete_cron_jobs();
  }
}

/**
 * Plugin loaded handler
 */
function altcha_plugin_loaded()
{
  $plugin = AltchaPlugin::$instance;
  $db_version = $plugin->get_db_version();
  if ($db_version === false || version_compare($db_version, ALTCHA_PLUGIN_VERSION, "<")) {
    altcha_run_migration();
    update_option(AltchaPlugin::$option_db_version, ALTCHA_PLUGIN_VERSION);
  }
}

/**
 * A new site is added (multi-site setup) 
 */
function altcha_new_site_handler($blog_id)
{
  if (is_plugin_active_for_network(plugin_basename(__FILE__))) {
    switch_to_blog($blog_id);
    $plugin = AltchaPlugin::$instance;
    $plugin->create_events_table();
    $plugin->create_cron_jobs();
    $plugin->ensure_default_options();
    restore_current_blog();
  }
}

/**
 * Run db migrations
 */
function altcha_run_migration() {
  $plugin = AltchaPlugin::$instance;
  if (is_multisite()) {
    $sites = get_sites();
    foreach ($sites as $site) {
      switch_to_blog($site->blog_id);
      $plugin->create_events_table();
      $plugin->create_cron_jobs();
      $plugin->ensure_default_options();
      restore_current_blog();
    }
  } else {
    $plugin->create_events_table();
    $plugin->create_cron_jobs();
    $plugin->ensure_default_options();
  }
}

/**
 * Replace "View Details" link
 */
function altcha_details_link($plugin_meta, $plugin_file)
{
  $plugin_basename = plugin_basename(__FILE__);
  if ($plugin_file === $plugin_basename) {
    foreach ($plugin_meta as $key => $meta) {
      if (strpos($meta, "plugin-information") !== false) {
        unset($plugin_meta[$key]);
        break;
      }
    }
    $plugin_meta[] = "<a href=\"https://altcha.org/docs/v2/wordpress\" target=\"_blank\">View Details</a>";
  }
  return $plugin_meta;
}

/**
 * Add admin menu
 */
function altcha_admin_menu()
{
  $plugin = AltchaPlugin::$instance;
  $sidebar = $plugin->get_settings("sidebar", true);
  if ($sidebar) {
    add_menu_page(
      "ALTCHA",                  // Page title
      "ALTCHA",                  // Menu title
      "manage_options",          // Capability required
      "altcha",                  // Menu slug
      "altcha_admin_page_content",    // Callback function
      "data:image/svg+xml;base64," . base64_encode('<svg viewBox="0 0 20 20" fill="none" xmlns="http://www.w3.org/2000/svg" width="20" height="20"><path d="M2.33955 16.4279C5.88954 20.6586 12.1971 21.2105 16.4279 17.6604C18.4699 15.947 19.6548 13.5911 19.9352 11.1365L17.9886 10.4279C17.8738 12.5624 16.909 14.6459 15.1423 16.1284C11.7577 18.9684 6.71167 18.5269 3.87164 15.1423C1.03163 11.7577 1.4731 6.71166 4.8577 3.87164C8.24231 1.03162 13.2883 1.4731 16.1284 4.8577C16.9767 5.86872 17.5322 7.02798 17.804 8.2324L19.9522 9.01429C19.7622 7.07737 19.0059 5.17558 17.6604 3.57212C14.1104 -0.658624 7.80283 -1.21043 3.57212 2.33956C-0.658625 5.88958 -1.21046 12.1971 2.33955 16.4279Z" fill="#f0f0f1"></path><path d="M3.57212 2.33956C1.65755 3.94607 0.496389 6.11731 0.12782 8.40523L2.04639 9.13961C2.26047 7.15832 3.21057 5.25375 4.8577 3.87164C8.24231 1.03162 13.2883 1.4731 16.1284 4.8577L13.8302 6.78606L19.9633 9.13364C19.7929 7.15555 19.0335 5.20847 17.6604 3.57212C14.1104 -0.658624 7.80283 -1.21043 3.57212 2.33956Z" fill="#f0f0f1"></path><path d="M7 10H5C5 12.7614 7.23858 15 10 15C12.7614 15 15 12.7614 15 10H13C13 11.6569 11.6569 13 10 13C8.3431 13 7 11.6569 7 10Z" fill="#f0f0f1"></path></svg>'),
      80                         // Position (optional)
    );
  } else {
    add_options_page(
      "ALTCHA",
      "ALTCHA",
      "manage_options",
      "altcha",
      "altcha_admin_page_content",
      30
    );
  }
}

/**
 * Render admin page content
 */
function altcha_admin_page_content()
{
  $plugin = AltchaPlugin::$instance;
  wp_enqueue_script(
    "altcha-admin-app",
    plugin_dir_url(__FILE__) . "public/vendor/admin-app.min.js",
    array(),
    AltchaPlugin::$version,
    true
  );
  wp_enqueue_script(
    "altcha-admin-wp",
    plugin_dir_url(__FILE__) . "public/admin-wp.js",
    array(),
    AltchaPlugin::$version,
    true
  );

  wp_localize_script("altcha-admin-wp", "pluginData", array(
    "altcha" => array(
      "eventsEnabled" => $plugin->get_settings("eventsEnabled", true),
      "installedPlugins" => $plugin->get_installed_plugins(),
      "license" => $plugin->get_license(),
      "underAttack" => $plugin->get_under_attack(),
    ),
  ));

  echo "<div id=\"altcha-app\">";
  echo "<div style=\"width:100%;min-height:100vh;display:flex;align-items:center;justify-content:center;\">Loading...</div>";
  echo "<noscript>JavaScript is required!</noscript>";
  echo "</div>";
}

/**
 * Enqueue interceptor
 */
function altcha_enqueue_interceptor_scripts()
{
  global $pagenow;
  $plugin = AltchaPlugin::$instance;
  $paths = $plugin->get_settings("paths", array());
  $license = $plugin->get_license();
  $admin_user = current_user_can("manage_options") || current_user_can("edit_posts");
  $bypass_cookies = $plugin->get_settings("bypassCookies");
  $bypass_ips = $plugin->get_settings("bypassIps");
  $bypass_users = $plugin->get_settings("bypassUsers") === true;
  $bypass = $admin_user
    || ($bypass_users && is_user_logged_in())
    || (is_array($bypass_cookies) && $plugin->match_cookies($bypass_cookies))
    || (is_array($bypass_ips) && $plugin->match_ip($plugin->get_ip_address(), $bypass_ips));
  $under_attack = $plugin->get_under_attack();
  $should_inject = false;
  if (!$bypass && in_array($pagenow, array("wp-login.php", "wp-register.php"))) {
    $should_inject = $plugin->get_settings("protectLogin") === true;
  } else if (!$bypass && $plugin->match_patterns($plugin->get_request_path(), $paths) !== false) {
    $should_inject = true;
  }
  $should_inject = apply_filters("altcha_inject", $should_inject, $bypass);
  if ($should_inject) {
    $invisible = !empty($license) && $plugin->get_settings("invisible");
    wp_enqueue_script(
      "altcha-widget",
      plugin_dir_url(__FILE__) . "public/altcha.min.js",
      array(),
      AltchaPlugin::$version,
      true
    );
    wp_enqueue_script(
      "altcha-interceptor",
      plugin_dir_url(__FILE__) . "public/vendor/interceptor.min.js",
      array(),
      AltchaPlugin::$version,
      true
    );
    wp_enqueue_style(
      "altcha-widget-styles",
      plugin_dir_url(__FILE__) . "public/altcha.css",
      array(),
      AltchaPlugin::$version,
      "all"
    );
    wp_enqueue_script(
      "altcha-interceptor-wp",
      plugin_dir_url(__FILE__) . "public/interceptor-wp.js",
      array(),
      AltchaPlugin::$version,
      true
    );
    wp_localize_script("altcha-interceptor-wp", "pluginData", array(
      "altcha" => array(
        "actions" => $plugin->get_settings("actions", array()),
        "paths" => $plugin->get_settings("paths", array()),
        "widget" => array(
          "challengeurl" => $plugin->get_challenge_url(),
          "delay" => $invisible ? 0 : 1000,
          "hidelogo" => $plugin->get_settings("hideLogo", false),
          "hidefooter" => $plugin->get_settings("hideFooter", false),
        ),
        "invisible" => $invisible,
        "cookiePath" => COOKIEPATH,
        "sitePath" => $plugin->get_site_path(),
        "protectLogin" => $plugin->get_settings("protectLogin", false),
        "underAttack" => $under_attack,
        "underAttackChallengeUrl" => $plugin->get_challenge_url(array(), true),
      ),
    ));
  }
  altcha_ensure_obfuscation_script_order();
}

function  altcha_enqueue_obfuscation_scripts()
{
  wp_enqueue_script(
    "altcha-obfuscation",
    plugin_dir_url(__FILE__) . "public/obfusication.min.js",
    array(),
    AltchaPlugin::$version,
    true
  );
}

function altcha_enqueue_widget_scripts()
{
  wp_enqueue_script(
    "altcha-widget",
    plugin_dir_url(__FILE__) . "public/altcha.min.js",
    array(),
    AltchaPlugin::$version,
    true
  );
  wp_enqueue_style(
    "altcha-widget-styles",
    plugin_dir_url(__FILE__) . "public/altcha.css",
    array(),
    AltchaPlugin::$version,
    "all"
  );
  wp_enqueue_script(
    "altcha-widget-wp",
    plugin_dir_url(__FILE__) . "public/widget-wp.js",
    array(
      "altcha-widget",
    ),
    AltchaPlugin::$version,
    true
  );
}

/**
 * The obfuscation plugin must be enqueued before the widget.
 * This function places the obfuscation script before the widget script if both are present.
 */
function altcha_ensure_obfuscation_script_order()
{
  $wp_scripts = wp_scripts();
  $queue = $wp_scripts->queue;
  $widget_script = "altcha-widget"; 
  $obfuscation_script = "altcha-obfuscation";
  $widget_index = array_search($widget_script, $queue);
  $obfuscation_index = array_search($obfuscation_script, $queue);
  if ($widget_index !== false && $obfuscation_index !== false) {
    unset($queue[$obfuscation_index]);
    $queue = array_values($queue);
    $widget_index = array_search($widget_script, $queue);
    array_splice($queue, $widget_index, 0, $obfuscation_script);
    $wp_scripts->queue = $queue;
  }
}

/**
 * Short code renderer
 */
function altcha_shortcode($attrs)
{
  $plugin = AltchaPlugin::$instance;
  return wp_kses(
    $plugin->get_widget_html(
      $plugin->get_widget_attrs($attrs),
    ),
    AltchaPlugin::$html_espace_allowed_tags,
  );
}

/**
 * CRON: delete expired events
 */
function altcha_delete_expired_events_callback()
{
  AltchaPlugin::$instance->delete_expired_events();
}

/**
 * Update major version warning
 */
function altcha_plugin_update_warning($plugin_data, $response)
{
  // Check if this is a major version update
  if (isset($response->new_version)) {
    $current_version = $plugin_data["Version"];
    $new_version = $response->new_version;
    $current_major = intval(explode(".", $current_version)[0]);
    $new_major = intval(explode(".", $new_version)[0]);

    if ($new_major > $current_major) {
      echo "<br><br><strong style=\"color: #d63638;\">⚠️ IMPORTANT:</strong> ";
      echo "This update contains major changes. Please review the update guide before updating.";
      echo " <a href=\"https://altcha.org\" target=\"_blank\">View update guide</a>.";
    }
  }
}

function altcha_plugin_settings_link($links)
{
  $url = esc_url(admin_url("admin.php?page=altcha"));
  $settings_link = "<a href=\"$url\">" . __("Settings") . '</a>';

  array_unshift(
    $links,
    $settings_link
  );
  return $links;
}

function altcha_dashboard_widget_callback()
{
  wp_enqueue_script(
    "altcha-admin-app",
    plugin_dir_url(__FILE__) . "public/vendor/admin-app.min.js",
    array(),
    AltchaPlugin::$version,
    true
  );
  wp_enqueue_script(
    "altcha-admin-wp",
    plugin_dir_url(__FILE__) . "public/admin-wp.js",
    array(),
    AltchaPlugin::$version,
    true
  );
  wp_localize_script("altcha-admin-wp", "pluginData", array(
    "altcha" => array(
      "pluginUrl" => admin_url("admin.php?page=altcha"),
      "underAttack" => AltchaPlugin::$instance->get_under_attack(),
    ),
  ));
  echo "<div id=\"altcha-dashboard-widget\" style=\"display:flex;flex-direction:column;min-height:250px\"><div>Loading...</div></div>";
}

function altcha_add_dashboard_widget()
{
  if (current_user_can("manage_options")) {
    // Add only for admins
    $enabled = AltchaPlugin::$instance->get_settings("eventsEnabled");
    if ($enabled) {
      wp_add_dashboard_widget(
        "altcha-widget",                     // Widget slug
        "ALTCHA",                            // Title
        "altcha_dashboard_widget_callback",  // Display function
        null,
        null,
        "normal",
        "high"
      );
    }
  }
}

if (!isset(AltchaPlugin::$instance)) {
  new AltchaPlugin();
}

if (is_admin()) {
  new AltchaPluginUpdater(
    __FILE__,
  );
}
