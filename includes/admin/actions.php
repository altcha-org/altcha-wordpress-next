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

add_action("wp_ajax_altcha_get_settings", "altcha_get_settings_ajax");
add_action("wp_ajax_altcha_set_settings", "altcha_set_settings_ajax");
add_action("wp_ajax_altcha_set_license", "altcha_set_license_ajax");
add_action("wp_ajax_altcha_set_under_attack", "altcha_set_under_attack_ajax");
add_action("wp_ajax_altcha_get_events", "altcha_get_events_ajax");
add_action("wp_ajax_altcha_get_analytics_data", "altcha_get_analytics_data_ajax");

function altcha_ajax_check_access()
{
  if (!defined("DOING_AJAX") || !DOING_AJAX) {
    wp_die("Invalid request", 400);
  }

  if (!current_user_can("manage_options")) {
    wp_die("Unauthorized", 401);
  }
}

function altcha_get_settings_ajax()
{
  $plugin = AltchaPlugin::$instance;
  altcha_ajax_check_access();
  wp_send_json_success(array(
    "defaults" => $plugin->get_default_settings(),
    "recommended" => array(
      "actions" => $plugin->get_default_actions(),
      "paths" => $plugin->get_default_paths(),
    ),
    "settings" => $plugin->get_settings()
  ));
}

function altcha_set_settings_ajax()
{
  altcha_ajax_check_access();
  if (!isset($_POST["data"])) {
    wp_send_json_error("No data received", 400);
    return;
  }

  $json_data = stripslashes(sanitize_text_field(wp_unslash($_POST["data"])));
  $data = json_decode($json_data, true);

  if (json_last_error() !== JSON_ERROR_NONE) {
    wp_send_json_error("Invalid JSON data", 400);
    return;
  }

  if (empty($data)) {
    wp_send_json_error("Empty data received", 400);
    return;
  }

  update_option(AltchaPlugin::$option_settings, json_encode($data));

  wp_send_json_success();
}

function altcha_set_license_ajax()
{
  altcha_ajax_check_access();

  update_option(AltchaPlugin::$option_license, isset($_POST["license"]) ? sanitize_text_field(wp_unslash($_POST["license"])) : null);

  wp_send_json_success();
}

function altcha_set_under_attack_ajax()
{
  altcha_ajax_check_access();

  update_option(AltchaPlugin::$option_under_attack, isset($_POST["under_attack"]) ? sanitize_text_field(wp_unslash($_POST["under_attack"])) : null);

  wp_send_json_success();
}

function altcha_get_events_ajax()
{
  altcha_ajax_check_access();
  wp_send_json_success(
    AltchaPlugin::$instance->get_events(array(
      "event" => isset($_POST["event"]) ? sanitize_text_field(wp_unslash($_POST["event"])) : null,
      "offset" => isset($_POST["offset"]) ? intval($_POST["offset"]) : null,
      "limit" => isset($_POST["limit"]) ? intval($_POST["limit"]) : null,
      "time" => isset($_POST["time"]) ? sanitize_text_field(wp_unslash($_POST["time"])) : null,
    )),
  );
}

function altcha_get_analytics_data_ajax()
{
  altcha_ajax_check_access();
  $settings = AltchaPlugin::$instance->get_settings();
  $enabled = isset($settings["eventsEnabled"]) && $settings["eventsEnabled"] === true;
  if (!$enabled) {
    return wp_send_json_success(array(
      "enabled" => false,
    ));
  }
  wp_send_json_success(
    AltchaPlugin::$instance->get_analytics_data(array(
      "time" => isset($_POST["time"]) ? sanitize_text_field(wp_unslash($_POST["time"])) : null,
      "tz_offset" => isset($_POST["tzOffset"]) ? (int) sanitize_text_field(wp_unslash($_POST["tzOffset"])) : null,
    )),
  );
}
