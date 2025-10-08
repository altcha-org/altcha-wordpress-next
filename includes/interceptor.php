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

$doing_cron = defined("DOING_CRON") && constant("DOING_CRON");
$wp_cli = defined("WP_CLI") && constant("WP_CLI");

if (!$doing_cron && !$wp_cli) {
  add_action("init", "altcha_interceptor", 1);
}

function altcha_interceptor()
{
  global $pagenow;
  $plugin = AltchaPlugin::$instance;
  $internal_actions = array(
    "altcha_get_analytics_data",
    "altcha_get_events",
    "altcha_get_settings",
    "altcha_set_settings",
    "heartbeat",
    "as_async_request_queue_runner",
    "wp_ajax_nopriv_as_async_request_queue_runner",
  );
  $internal_scripts = array(
    "cron.php",
    "wp-cron.php",
  );
  $cookie_name = "altcha";
  $script_name = $pagenow;
  $action = null;
  $form_id = null;
  $payload = null;
  $firewall = null;
  $params = array();
  $path = $plugin->get_request_path();
  $under_attack = $plugin->get_under_attack();

  if (empty($script_name) && isset($_SERVER["SCRIPT_NAME"])) {
    $script_name = basename(wp_parse_url($_SERVER["SCRIPT_NAME"], PHP_URL_PATH));
  }

  if (!empty($script_name) && in_array($script_name, $internal_scripts)) {
    // Bypass for internal scripts
    return;
  }

  if (str_ends_with($path, "/altcha/v1/challenge")) {
    // Always allow challenge API endpoint
    return;
  }

  $actions_list = $plugin->get_settings("actions", array());
  $paths_list = $plugin->get_settings("paths", array());
  $protect_login = $plugin->get_settings("protectLogin") === true;
  $bypass_ips = $plugin->get_settings("bypassIps");
  $bypass_users = $plugin->get_settings("bypassUsers") === true;
  $sentinel_score_block = intval($plugin->get_settings("sentinelScoreBlock", 0));

  if (current_user_can("manage_options") || current_user_can("edit_posts")) {
    // Bypass for admins and users with access to the admin section
    return;
  }

  if ($bypass_users === true && is_user_logged_in()) {
    // Bypass for any logged-in user
    return;
  }

  if (!empty($script_name) && in_array($script_name, array("wp-login.php", "wp-register.php")) && $protect_login !== true) {
    // Bypass for login page
    return;
  }

  if (is_array($bypass_ips) && $plugin->match_ip($plugin->get_ip_address(), $bypass_ips)) {
    // Bypass for whitelisted IPs
    return;
  }

  if ($plugin->match_patterns($plugin->get_request_path(), $paths_list) === false) {
    // Bypass for whitelisted path
    return;
  }

  $action = altcha_interceptor_detect_action($actions_list);

  if ($action === false) {
    // Bypass for whitelisted action
    return;
  }

  if ($script_name === "admin-ajax.php") {
    $form_id = get_query_var("form_id");

    if (isset($_GET["form_id"])) {
      $form_id = sanitize_text_field(wp_unslash($_GET["form_id"]));
    }

    // Find form_id in POST data, supports prefixed names such as 
    foreach ($_POST as $key => $value) {
      if (preg_match("/form_id$/i", $key)) {
        $form_id = $value;
        break;
      }
    }

    if (!empty($action) && in_array($action, $internal_actions)) {
      // Bypass for internal actions
      return;
    }
  } else if (!empty($script_name) && in_array($script_name, array("wp-login.php", "wp-register.php")) && $protect_login === true && !empty($action) && $plugin->match_patterns($action, $actions_list) === false) {
    // Bypass for whitelisted login action
    return;
  }

  ////////
  // Under Attack
  ///////
  if ($under_attack) {
    $under_attack_payload = null;
    $under_attack_params = array();

    if (empty($payload) && isset($_COOKIE["altcha_under_attack"])) {
      $under_attack_payload = sanitize_text_field(wp_unslash($_COOKIE["altcha_under_attack"]));
    }

    // Don't store used challenge - it's reusable until expiration
    if ($under_attack_payload && $plugin->verify($under_attack_payload, $under_attack_params, null, false) === true) {
      if (!isset($under_attack_params["ip"]) || $under_attack_params["ip"] === $plugin->get_ip_address_hash()) {
        $timezone = isset($_COOKIE["altcha_under_attack_tz"]) ? sanitize_text_field(wp_unslash($_COOKIE["altcha_under_attack_tz"])) : null;

        ////////
        // Firewall (Under Attack Mode)
        ///////
        $firewall = altcha_apply_firewall($timezone);
        if ($firewall["allow"] !== true) {
          $plugin->log_event("blocked", null, null, $firewall["reason"], $timezone, true, null);
          header("X-Blocked-Reason: {$firewall["reason"]}");
          return altcha_interceptor_error_response("[ALTCHA] Sorry, your request could not be processed.", $firewall["reason"]);
        }
        ///////
      }

    } else {
      require plugin_dir_path(__FILE__) . "/underattack.php";
      exit;
    }
  }
  ////////

  if (isset($_SERVER["REQUEST_METHOD"]) && $_SERVER["REQUEST_METHOD"] !== "POST" && $script_name !== "admin-ajax.php") {
    // Bypass for non-POST requests
    return;
  }

  ////////
  // Rate Limiter
  if (altcha_apply_rate_limits($path, $action) === false) {
    return;
  }
  ////////

  // Get payload from cookie
  if (empty($payload) && isset($_COOKIE[$cookie_name])) {
    $payload = sanitize_text_field(wp_unslash($_COOKIE[$cookie_name]));

    // Delete used payload cookie
    setcookie($cookie_name, "", time() - 3600, COOKIEPATH); 
  }

  // Get payload from the POST data (widget mode)
  if (empty($payload) && isset($_POST["altcha"])) {
    $payload = sanitize_text_field(wp_unslash($_POST["altcha"]));
  }

  if (empty($payload)) {
    return altcha_interceptor_fail("failed", "ALTCHA payload missing.", $action, $form_id);
  }

  $payload_data = $plugin->parse_payload($payload, $params);
  $timezone = isset($params["params_tz"]) ? $params["params_tz"] : null;
  $interceptor = isset($params["params_interceptor"]) ? $params["params_interceptor"] === "1" : null;
  $plugin_name = isset($params["params_plugin"]) ? $params["params_plugin"] : null;
  $challenge_action = isset($params["params_action"]) ? $params["params_action"] : null;
  $form_id = isset($params["params_form_id"]) ? $params["params_form_id"] : $form_id;
  $verification_data = isset($params["verification_data"]) ? $params["verification_data"] : null;

  ////////
  // Firewall
  ///////
  if ($firewall === null) {
    $firewall = altcha_apply_firewall($timezone);
    if ($firewall["allow"] !== true) {
      header("X-Blocked-Reason: {$firewall["reason"]}");
      return altcha_interceptor_fail("blocked", $firewall["reason"], $action, $form_id, $timezone, $interceptor, $plugin_name, $verification_data);
    }
  }
  ////////

  if (apply_filters("altcha_intercept", true, $payload_data, $params) === false) {
    // Bypass if the filter returns false
    return;
  }

  ////////
  // Verification
  ///////
  $verified = $plugin->verify($payload_data, $params) === true;
  ///////

  if (!empty($challenge_action) && !empty($action) && $challenge_action !== $action) {
    // Action does not match with payload
    return altcha_interceptor_fail("failed", "Invalid action.", $challenge_action, $form_id, $timezone, $interceptor, $plugin_name);
  }

  if (!empty($params) && !empty($params["under_attack"])) {
    // Cannot use under attack payload
    return altcha_interceptor_fail("failed", "Invalid ALTCHA payload.", $challenge_action, $form_id, $timezone, $interceptor, $plugin_name);
  }

  if ($sentinel_score_block > 0 && isset($verification_data["score"]) && intval($verification_data["score"]) >= $sentinel_score_block) {
    // Block when classified as spam
    return altcha_interceptor_fail("blocked", "Sentinel score is {$verification_data["score"]}.", $action, $form_id, $timezone, $interceptor, $plugin_name, $verification_data);
  }

  // Prefer to log the current action if challenge action is not set
  $action = !empty($action) ? $action : $challenge_action;

  if (empty($action) && $script_name !== "admin-ajax.php") {
    // Use method as a fallback action name
    $action = $_SERVER["REQUEST_METHOD"];
  }

  if ($verified) {
    $plugin->log_event("verified", $action, $form_id, null, $timezone, $interceptor, $plugin_name, $verification_data);
  } else {
    return altcha_interceptor_fail("failed", "Invalid ALTCHA payload.", $action, $form_id, $timezone, $interceptor, $plugin_name, $verification_data);
  }
}

function altcha_apply_firewall(string|null $timezone): array
{
  $plugin = AltchaPlugin::$instance;

  if (!$plugin->get_license()) {
    return array(
      "allow" => true,
    );
  }

  $ip = $plugin->get_ip_address();
  $ip_country = $plugin->get_ip_country(); 
  $user_agent = isset($_SERVER["HTTP_USER_AGENT"]) ? sanitize_text_field(wp_unslash($_SERVER["HTTP_USER_AGENT"])) : null;
  $block_countries = $plugin->get_settings("blockCountries", array());
  $block_ips = $plugin->get_settings("blockIps", array());
  $block_user_agents = $plugin->get_settings("blockUserAgents", array());
  $country = $ip_country ?? ($timezone ? $plugin->get_timezone_country($timezone) : null);

  // Block countries
  if ($country && in_array($country, $block_countries)) {
    return array(
      "allow" => false,
      "reason" => "ALTCHA Firewall: Country {$country} is blocked.",
    );
  }

  // Blocks IPs
  if ($ip && $plugin->match_ip($ip, $block_ips) === true) {
    return array(
      "allow" => false,
      "reason" => "ALTCHA Firewall: IP {$ip} is blocked.",
    );
  }

  // Block User-Agents
  if ($user_agent && $plugin->match_patterns($user_agent, $block_user_agents) === true) {
    return array(
      "allow" => false,
      "reason" => "ALTCHA Firewall: User-Agent is blocked.",
    );
  }

  return array(
    "allow" => true,
  );
}

function altcha_apply_rate_limits(string $path, string|null $action = null): bool
{
  $plugin = AltchaPlugin::$instance;

  if (!$plugin->get_license()) {
    return true;
  }

  $rate_limits = $plugin->get_settings("rateLimits", array());
  $headers_set = false;
  $user_id = get_current_user_id();

  foreach ($rate_limits as $rt) {
    $rt_value = $plugin->parse_rate_limit($rt["value"]);
    $key = null;
    if ($rt_value) {
      if ($rt["key"] === "user_id" && $user_id) {
        $key = $user_id;
      } else if ($rt["key"] === "ip") {
        $key = $plugin->get_ip_address();
      } else {
        $key = $plugin->get_edk();
      }
      if ($rt["kind"] === "action" && $rt["discriminator"] && (!$action || !$plugin->match_patterns($action, array($rt["discriminator"])))) {
        continue;
      }
      if ($rt["kind"] === "path" && $rt["discriminator"] && !$plugin->match_patterns($path, array($rt["discriminator"]))) {
        continue;
      }
      $limit = $plugin->rate_limit($rt_value["limit"], $rt_value["interval"], $key);
      if (!$headers_set) {
        header("X-RateLimit-Limit: {$rt_value["limit"]}");
        header("X-RateLimit-Remaining: {$limit["remaining"]}");
        header("X-RateLimit-Reset-In: {$limit["reset_in"]}");
        $headers_set = true;
      }
      if ($limit["exceeded"] === true) {
        altcha_interceptor_error_response(
          "ALTCHA Firewall: rate limit exceeded.",
          null,
          429,
        );
        return false;
      }
    }
  }
  return true;
}

// Supports custom action names using pattern `{field_name}={value}`
function altcha_interceptor_detect_action(array $patterns, string $default_action_name = "action")
{
  $name = "";
  $matched = false;
  foreach ($patterns as $pattern) {
    $name = "action";
    $isNegation = str_starts_with($pattern, "!");
    $cleanPattern = $isNegation ? substr($pattern, 1) : $pattern;
    if (strpos($cleanPattern, "=") > 0) {
      $parts = explode("=", $cleanPattern);
      $name = $parts[0];
      $cleanPattern = $parts[1];
    }
    $regex = "/^" . str_replace("\*", ".*", preg_quote($cleanPattern, "/")) . "$/i";
    $value = isset($_POST[$name]) ? sanitize_text_field(wp_unslash($_POST[$name])) : (isset($_GET[$name]) ? sanitize_text_field(wp_unslash($_GET[$name])) : "");
    if (($name === $default_action_name || !empty($value)) && preg_match($regex, $value)) {
      if ($isNegation) {
        return false;
      }
      $matched = $name === $default_action_name ? $value : $name . "=" . $value;
    }
  }
  return $matched;
}

function altcha_interceptor_fail(string $event, string $reason, string|null $action = null, string|null $form_id = null, string|null $timezone = null, bool|null $interceptor = false, string|null $plugin_name = null, array|null $verification_data = null)
{
  $plugin = AltchaPlugin::$instance;
  $plugin->log_event($event, $action, $form_id, $reason, $timezone, $interceptor, $plugin_name, $verification_data);

  altcha_interceptor_error_response(
    "[ALTCHA] Sorry, your request could not be processed.",
    $reason,
  );
}

function altcha_interceptor_error_response(string $message, string|null $reason = null, int $status = 403)
{
  if (AltchaPlugin::$is_ajax) {
    $response = array(
      "data" => array(
        "errors" => array("generic" => $reason ?? "Verification failed."),
        "message" => $message,
        "notice" => "error",
        "success" => false,
      ),
      "error" => "altcha_verification_failed",
      "message" => $message,
      "status" => "error",
      "success" => false,
    );
    header("Content-Type: application/json");
    echo json_encode($response);
    exit;
  } else {
    status_header($status);
    wp_die(
      esc_html($message),
      esc_html($message),
      array("response" => esc_html($status))
    );
  }
}
