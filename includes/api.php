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

add_action(
  "rest_api_init",
  function () {
    register_rest_route("altcha/v1", "challenge", array(
      "methods"   => WP_REST_Server::READABLE,
      "callback"  => "altcha_generate_challenge_endpoint",
      "permission_callback" => "__return_true"
    ));
  }
);

function altcha_generate_challenge_endpoint()
{
  $plugin = AltchaPlugin::$instance;
  $under_attack_threat_level = $plugin->get_under_attack();
  $action = isset($_GET["params_action"])  ? sanitize_text_field(wp_unslash($_GET["params_action"])) : null;
  $form_id = isset($_GET["params_form_id"]) ? sanitize_text_field(wp_unslash($_GET["params_form_id"])) : null;
  $timezone = isset($_GET["params_tz"]) ? sanitize_text_field(wp_unslash($_GET["params_tz"])) : null;
  $plugin_name = isset($_GET["params_plugin"]) ? sanitize_text_field(wp_unslash($_GET["params_plugin"])) : null;
  $interceptor = isset($_GET["params_interceptor"]) ? sanitize_text_field(wp_unslash($_GET["params_interceptor"])) : null;
  $under_attack = isset($_GET["params_under_attack"]) ? sanitize_text_field(wp_unslash($_GET["params_under_attack"])) : null;
  $complexity = "low";
  $expires = null;
  $should_log = true;

  if ($under_attack_threat_level) {
    $complexity = $under_attack_threat_level;
  }

  if ($under_attack_threat_level && $under_attack) {
    $expires = $plugin->get_under_attack_cookie_expire();
    // Disable challenge logging when under attack
    $should_log = false;
  }

  $resp = new WP_REST_Response($plugin->generate_challenge(null, $complexity, $expires, array(
    "params.action" => $action,
    "params.form_id" => $form_id,
    "params.interceptor" => $interceptor,
    "params.plugin" => $plugin_name,
    "params.tz" => $timezone,
    "params.under_attack" => $under_attack,
  )));
  $resp->set_headers(array(
    "Cache-Control" => "no-cache, no-store, max-age=0",
    "X-Robots-Tag" => "noindex, nofollow",
  ));

  if ($should_log) {
    $plugin->log_event("challenge", $action, $form_id, null, $timezone, $interceptor === "1", $plugin_name);
  }

  return $resp;
}
