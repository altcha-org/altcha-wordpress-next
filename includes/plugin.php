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

if (!defined("ABSPATH")) exit;

class AltchaPlugin
{
  public static $instance;

  public static $version = ALTCHA_PLUGIN_VERSION;

  public static $option_db_version = "altcha_db_version";

  public static $option_license = "altcha_license";

  public static $option_hashing_secret = "altcha_hashing_secret";

  public static $option_secret = "altcha_secret";

  public static $option_settings = "altcha_settings";

  public static $option_under_attack = "altcha_under_attack";

  public static $table_events = "altcha_events";

  public static $transient_prefix = "altcha_";

  public static $free_max_data_retention_days = 7;

  public static $timezones = null;

  public static $ip_address = null;

  public static $ip_country = null;

  public static $is_ajax = false;

  public static $widget_cdn_url = "https://cdn.jsdelivr.net/gh/altcha-org/altcha@2.2.4/dist/altcha.i18n.min.js";

  public static $max_body_size = 4096; // 4kB

  public static $filterable_events_fields = array(
    "event",
    "ip_address",
    "action",
    "plugin",
    "reason",
    "timezone",
    "user_id",
    "user_agent",
    "form_id",
    "country",
    "referrer",
    "url"
  );

  public static $filterable_events_search_fields = array(
    "action",
    "reason",
    "timezone",
    "user_agent",
    "referrer",
    "url"
  );

  public static $html_espace_allowed_tags = array(
    "altcha-widget" => array(
      "debug" => array(),
      "challengeurl" => array(),
      "customfetch" => array(),
      "strings" => array(),
      "auto" => array(),
      "floating" => array(),
      "delay" => array(),
      "hidelogo" => array(),
      "hidefooter" => array(),
      "language" => array(),
      "name" => array(),
      "overlay" => array(),
    ),
    "div" => array(
      "class" => array(),
      "style" => array(),
    ),
    "input" => array(
      "class" => array(),
      "id" => array(),
      "name" => array(),
      "type" => array(),
      "value" => array(),
      "style" => array(),
    ),
    "noscript" => array(),
  );

  public array|null $settings_json = null;

  public bool $verified = false;

  public array|null $verification_data = null;

  public function __construct()
  {
    self::$instance = $this;
    self::$timezones = require plugin_dir_path(__FILE__) . "timezones.php";
    self::$is_ajax =
      (isset($_SERVER["HTTP_X_REQUESTED_WITH"])
        && strtolower($_SERVER["HTTP_X_REQUESTED_WITH"]) === "xmlhttprequest")
      || (isset($_SERVER["HTTP_SEC_FETCH_MODE"])
        && in_array(strtolower($_SERVER["HTTP_SEC_FETCH_MODE"]), array("cors", "same-origin")))
      || (defined("DOING_AJAX") && constant("DOING_AJAX"));
  }

  public function anonymize_ip(string $ip): string
  {
    if (empty($ip) || !filter_var($ip, FILTER_VALIDATE_IP)) {
      return "0.0.0.0";
    }
    if ($ip === "::1" || $ip === "127.0.0.1") {
      return $ip;
    }
    return preg_replace("/([.:])\w+$/", "$1", $ip) . "0";
  }

  public function check_used_challenge(string $challenge): bool
  {
    return get_transient($this->get_transient_key("ch_" . $challenge)) === "1";
  }

  public function create_events_table()
  {
    global $wpdb;
    $charset_collate = $wpdb->get_charset_collate();
    $table_name = $wpdb->prefix . self::$table_events;
    $sql = "CREATE TABLE $table_name (
      id bigint(20) NOT NULL AUTO_INCREMENT,
      timestamp datetime NOT NULL,
      timestamp_u bigint(20) NOT NULL,
      action varchar(100),
      body text,
      country varchar(2),
      event varchar(20) NOT NULL,
      form_id varchar(100),
      interceptor tinyint(1) NOT NULL default '0',
      plugin varchar(100),
      reason varchar(100),
      referrer varchar(255),
      timezone varchar(100),
      url varchar(255),
      user_id bigint(20) NOT NULL default '0',
      ip_address varchar(45) NOT NULL,
      user_agent varchar(255) NOT NULL,
      verification_data json,
      PRIMARY KEY (id),
      KEY action (action),
      KEY event (event),
      KEY timestamp_u (timestamp_u)
    ) $charset_collate;";
    require_once(ABSPATH . "wp-admin/includes/upgrade.php");
    dbDelta($sql);
  }

  public function create_cron_jobs()
  {
    if (!wp_next_scheduled("altcha_delete_expired_events")) {
      wp_schedule_event(time(), "daily", "altcha_delete_expired_events");
    }
  }

  public function delete_cron_jobs()
  {
    $timestamp_events = wp_next_scheduled("altcha_delete_expired_events");
    if ($timestamp_events) {
      wp_unschedule_event($timestamp_events, "altcha_delete_expired_events");
    }
  }

  public function delete_expired_events()
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_events;
    $days = intval($this->get_settings("eventsRetentionDays", 90), 10);
    if (!$this->get_license()) {
      $days = self::$free_max_data_retention_days;
    }
    $wpdb->query(
      $wpdb->prepare(
        "DELETE FROM $table_name WHERE timestamp_u < %d",
        time() - ($days * DAY_IN_SECONDS),
      )
    );
  }

  public function ensure_default_options()
  {
    if (get_option(self::$option_secret) === false) {
      update_option(self::$option_secret, $this->random_secret());
    }
    if (get_option(self::$option_hashing_secret) === false) {
      update_option(self::$option_hashing_secret, $this->random_secret());
    }
    if (get_option(self::$option_settings) === false) {
      update_option(self::$option_settings, json_encode($this->get_default_settings()));
    } else {
      update_option(self::$option_settings, json_encode(array_merge(
        array(
          "eventsLogFailedBody" => false, // Added in 2.2.0
          "challengeExpiration" => 300, // Added in 2.5.0
        ),
        $this->get_settings()
      )));
    }
  }

  public function get_analytics_data($options): array
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_events;
    $tz_offset = isset($options["tz_offset"]) ? $options["tz_offset"] : $this->get_timezone_offset();
    $time = $this->get_time_start_end(isset($options["time"]) ? $options["time"] : null, $tz_offset);
    $time_end = $time["end"];
    $time_start = $time["start"];
    if (!$this->get_license()) {
      $threshold_time = time() - (self::$free_max_data_retention_days * DAY_IN_SECONDS);
      $time_end = max($time_end, $threshold_time);
      $time_start = max($time_start, $threshold_time);
    }
    $where = "WHERE timestamp_u >= %d AND timestamp_u <= %d";
    $events = $wpdb->get_results($wpdb->prepare("
      SELECT 
        event,
        FLOOR((timestamp_u + %d) / %d) * %d AS time,
        COUNT(*) as count
      FROM $table_name
      $where
      GROUP BY time, event
      ORDER BY time ASC
    ", [$time["interval"] >= 86_400 ? $tz_offset : 0, $time["interval"], $time["interval"], $time_start, $time_end]));
    $plugins = $wpdb->get_results($wpdb->prepare("
      SELECT 
        plugin,
        COUNT(*) as count
      FROM $table_name
      $where
        AND event != 'bot'
      GROUP BY plugin
      ORDER BY count ASC
    ", [$time_start, $time_end]));
    $actions = $wpdb->get_results($wpdb->prepare("
      SELECT 
        action,
        event,
        COUNT(*) as count
      FROM $table_name
      $where
        AND event != 'bot'
      GROUP BY event, action
      ORDER BY count ASC
    ", [$time_start, $time_end]));
    return array(
      "actions" => $actions,
      "events" => $events,
      "interval" => $time["interval"],
      "plugins" => $plugins,
      "time_start" => $time["start"],
      "time_end" => $time["end"],
      "tz_offset" => $tz_offset,
    );
  }

  public function get_challenge_url(array $params = array(), bool $local = false): string
  {
    $mode = $this->get_settings("mode");
    if ($mode === "sentinel" && !$local) {
      $challenge_url = $this->get_settings("sentinelUrl");
    } else {
      $challenge_url = rest_url("/altcha/v1/challenge");
    }
    if (count($params)) {
      $parsed_url = wp_parse_url($challenge_url);
      $existing_params = [];
      if (isset($parsed_url["query"])) {
        parse_str($parsed_url["query"], $existing_params);
      }
      $all_params = array_merge($existing_params, $params);
      $new_query = http_build_query($all_params);
      $challenge_url = explode("?", $challenge_url)[0] . "?" . $new_query;
    }
    return apply_filters("altcha_get_challenge_url", $challenge_url);
  }

  public function get_complexity(string|int|null $complexity): array
  {
    if ($complexity === null) {
      $complexity = "low";
    }
    switch ($complexity) {
      case 1:
      case "minimal":
        $min = 10;
        $max = 200;
        break;
      case 2:
      case "low":
        $min = 200;
        $max = 2000;
        break;
      case 3:
      case "medium":
        $min = 2000;
        $max = 20000;
        break;
      case 4:
      case "high":
        $min = 20000;
        $max = 50000;
        break;
      case 5:
      case "very_high":
        $min = 70000;
        $max = 150000;
        break;
      default:
        $min = 200;
        $max = 1000;
    }
    $result = array(
      "max" => $max,
      "min" => $min,
    );
    return apply_filters("altcha_get_complexity", $result);
  }

  public function get_current_url()
  {
    $request_uri = isset($_SERVER["REQUEST_URI"]) ? sanitize_url(wp_unslash($_SERVER["REQUEST_URI"])) : "/";
    // Remove WordPress installation directory if it appears twice
    $wp_path = wp_parse_url(home_url(), PHP_URL_PATH);
    if ($wp_path && $wp_path !== "/" && strpos($request_uri, $wp_path) === 0) {
      $request_uri = substr($request_uri, strlen($wp_path));
    }
    return home_url($request_uri);
  }

  public function get_db_version(): string
  {
    return get_option(self::$option_db_version);
  }

  public function get_default_settings(): array
  {
    return array(
      "mode" => "standard",
      "actions" => array_merge(...array_values($this->get_default_actions())),
      "paths" => array_merge(...array_values($this->get_default_paths())),
      "eventsEnabled" => true,
      "eventsRetentionDays" => self::$free_max_data_retention_days,
      "eventsLogBlocked" => true,
      "eventsLogChallenges" => true,
      "eventsLogFailedBody" => false,
      "eventsAnonymizeIps" => true,
      "protectLogin" => true,
      "challengeExpiration" => 300,
    );
  }

  public function get_default_actions(): array
  {
    $installed_plugins = $this->get_installed_plugins();
    $actions = array(
      "" => array(
        "*",
      ),
    );
    if (in_array("elementor", $installed_plugins) || in_array("elementor-pro", $installed_plugins)) {
      $actions["Elementor"] = array(
        "!elementor_js_log",
      );
    }
    if (in_array("eventprime", $installed_plugins)) {
      $actions["EventPrime"] = array(
        "ep_save_event_booking",
        "!ep_*",
        "!ep_event_booking_data=*",
      );
    }
    if (in_array("forminator", $installed_plugins)) {
      $actions["Forminator"] = array(
        "!forminator_get_nonce",
      );
    }
    if (in_array("pixelyoursite", $installed_plugins)) {
      $actions["PixelYourSite"] = array(
        "!pys_get_pbid",
      );
    }
    if (in_array("translatepress-multilingual", $installed_plugins)) {
      $actions["TranslatePress"] = array(
        "!trp_*",
      );
    }
    if (in_array("woocommerce", $installed_plugins)) {
      $actions["WooCommerce"] = array(
        "!wc-ajax=*",
        "!wc-api=*",
        "!*_wc_privacy_cleanup",
      );
    }
    if (in_array("wordfence", $installed_plugins)) {
      $actions["Wordfence"] = array(
        "!wordfence_ls_authenticate",
      );
    }
    if (in_array("wpdiscuz", $installed_plugins)) {
      $actions["wpDiscuz"] = array(
        "!wpdCheckNotificationType",
      );
    }
    return $actions;
  }

  public function get_default_paths(): array
  {
    $installed_plugins = $this->get_installed_plugins();
    $paths = array(
      "" => array(
        "*"
      ),
    );
    if (in_array("eventprime", $installed_plugins)) {
      $paths["EventPrime"] = array(
        "!" . $this->normalize_path("/event-organizers/*"),
        "!" . $this->normalize_path("/venues/*"),
      );
    }
    if (in_array("metform", $installed_plugins)) {
      $paths["MetForm"] = array(
        "!" . $this->normalize_path(wp_parse_url(rest_url("/metform/v1/forms/views/*"), PHP_URL_PATH)),
      );
    }
    if (in_array("woocommerce", $installed_plugins)) {
      $paths["WooCommerce"] = array(
        $this->normalize_path(wp_parse_url(rest_url("/wc/store/v1/checkout"), PHP_URL_PATH)),
        "!" . $this->normalize_path(wp_parse_url(rest_url("/wc/store/v1/*"), PHP_URL_PATH)),
        "!" . $this->normalize_path("/wc-api/*"),
      );
    }
    if (in_array("real-cookie-banner", $installed_plugins)) {
      $paths["Real Cookie Banner"] = array(
        "!" . $this->normalize_path(wp_parse_url(rest_url("/real-cookie-banner/v1/consent"), PHP_URL_PATH)),
        "!" . $this->normalize_path(wp_parse_url(rest_url("/*/consent"), PHP_URL_PATH)),
      );
    }
    return $paths;
  }

  public function get_edk(): string
  {
    $salt = $this->get_hashing_secret();
    $time = $this->round_time(time(), $this->get_timezone_offset(), DAY_IN_SECONDS);
    $ip = $this->get_ip_address();
    $user_agent = isset($_SERVER["HTTP_USER_AGENT"]) ? sanitize_text_field(wp_unslash($_SERVER["HTTP_USER_AGENT"])) : "";
    $accept_language = isset($_SERVER["HTTP_ACCEPT_LANGUAGE"]) ? sanitize_text_field(wp_unslash($_SERVER["HTTP_ACCEPT_LANGUAGE"])) : "";
    $ip_hash = substr(hash("sha256", $time . $salt . $ip), 0, 16);
    $header_hash = substr(hash("sha256", $time . $salt . $ip . $user_agent . $accept_language), 0, 16);
    return $ip_hash . $header_hash;
  }

  public function get_events($options): array
  {
    global $wpdb;
    $table_name = $wpdb->prefix . self::$table_events;
    $offset = isset($options["offset"]) ? $options["offset"] : 0;
    $limit = isset($options["limit"]) ? $options["limit"] : 20;
    $time = $this->get_time_start_end(isset($options["time"]) ? $options["time"] : null);
    if (!$this->get_license()) {
      $threshold_time = time() - (self::$free_max_data_retention_days * DAY_IN_SECONDS);
      $time["end"] = max($time["end"], $threshold_time);
      $time["start"] = max($time["start"], $threshold_time);
    }
    $where = "WHERE timestamp_u >= %d AND timestamp_u <= %d";
    $params = [$time["start"], $time["end"]];
    if (!empty($options["filters"]) && is_array($options["filters"])) {
      $anonymize_ips = $this->get_settings("eventsAnonymizeIps") === true;
      foreach ($options["filters"] as $filter_item) {
        $field = trim($filter_item["field"]);
        $value = empty($filter_item["value"]) ? null : trim($filter_item["value"]);
        if (!empty($field) && in_array($field, self::$filterable_events_fields)) {
          if ($value !== null && $field === "ip_address" && $anonymize_ips) {
            $value = $this->anonymize_ip($value);
          }
          if ($value === null) {
            $where .= " AND %i IS NULL";
            $params[] = $field;
          } else if (in_array($field, self::$filterable_events_search_fields)) {
            $where .= " AND %i LIKE %s";
            $params[] = $field;
            $params[] = "%" . $wpdb->esc_like($value) . "%";
          } else {
            $where .= " AND %i = %s";
            $params[] = $field;
            $params[] = $value;
          }
        }
      }
    } else if (!empty($options["event"])) {
      $where .= " AND event = %s";
      $params[] = $options["event"];
    }
    $events = $wpdb->get_results($wpdb->prepare("
      SELECT
        *, 
        DATE_FORMAT(timestamp, '%%Y-%%m-%%dT%%TZ') AS timestamp_iso
      FROM $table_name
      $where
      ORDER BY id DESC
      LIMIT $offset, $limit
    ", $params));
    $total = $wpdb->get_results($wpdb->prepare("
      SELECT COUNT(*) as count FROM $table_name
      $where
    ", $params));
    return array(
      "events" => array_map(function ($event) {
        $event->interceptor = $event->interceptor === "1" || $event->interceptor === 1;
        return $event;
      }, $events),
      "total" => $total[0]->count,
    );
  }

  public function get_hashing_secret(): string
  {
    return trim(get_option(self::$option_hashing_secret));
  }

  public function get_installed_plugins(): array
  {
    $plugins = array(
      // general
      "elementor" => $this->is_plugin_installed("elementor.php"),
      "elementor-pro" => $this->is_plugin_installed("elementor-pro.php"),
      "eventprime" => $this->is_plugin_installed("event-prime.php"),
      "formidable" => $this->is_plugin_installed("formidable.php"),
      "forminator" => $this->is_plugin_installed("forminator.php"),
      "gravityforms" => $this->is_plugin_installed("gravityforms.php"),
      "mainwp" => $this->is_plugin_installed("mainwp.php"),
      "metform" => $this->is_plugin_installed("metform.php"),
      "pixelyoursite" => $this->is_plugin_installed("pixelyoursite/facebook-pixel-master.php"),
      "translatepress-multilingual" => $this->is_plugin_installed("translatepress-multilingual/index.php"),
      "wpdiscuz" => $this->is_plugin_installed("class.WpdiscuzCore.php"),
      "wpforms" => $this->is_plugin_installed("wpforms.php"),
      "wp-rocket" => $this->is_plugin_installed("wp-rocket.php"),
      // e-commerce
      "easy-digital-downloads" => $this->is_plugin_installed("easy-digital-downloads.php"),
      "memberpress" => $this->is_plugin_installed("memberpress.php"),
      "professional-ecommerce" => $this->is_plugin_installed("marketpress.php"),
      "surecart" => $this->is_plugin_installed("surecart.php"),
      "woocommerce" => $this->is_plugin_installed("woocommerce.php"),
      "wp-easycart" => $this->is_plugin_installed("wp-easycart.php"),
      // legal
      "iubenda-cookie-law-solution" => $this->is_plugin_installed("iubenda_cookie_solution.php"),
      "complianz-gdpr" => $this->is_plugin_installed("complianz-gpdr.php"),
      "cookie-law-info" => $this->is_plugin_installed("cookie-law-info.php"),
      "wplegalpages" => $this->is_plugin_installed("wplegalpages.php"),
      "gdpr-cookie-compliance" => $this->is_plugin_installed("moove-gdpr.php"),
      "real-cookie-banner" => $this->is_plugin_installed("real-cookie-banner/index.php"),
      // notifications
      "notificationx" => $this->is_plugin_installed("notificationx.php"),
      "notifal" => $this->is_plugin_installed("notifal.php"),
      // security
      "wordfence" => $this->is_plugin_installed("wordfence.php"),
    );
    return array_keys(array_filter($plugins));
  }

  public function get_ip_address(): string|null
  {
    if (!empty(self::$ip_address)) {
      return self::$ip_address;
    }
    $ips = [];
    if (!empty($_SERVER["HTTP_X_FORWARDED_FOR"])) {
      $headerIps = explode(",", sanitize_text_field(wp_unslash($_SERVER["HTTP_X_FORWARDED_FOR"])));
      foreach ($headerIps as $headerIp) {
        $headerIp = trim($headerIp);
        $normalizedIp = $this->normalize_ip($headerIp);
        if ($normalizedIp) {
          $ips[] = $normalizedIp;
        }
      }
    }
    if (!empty($_SERVER["REMOTE_ADDR"])) {
      $normalizedRemoteAddr = $this->normalize_ip(sanitize_text_field(wp_unslash($_SERVER["REMOTE_ADDR"])));
      if ($normalizedRemoteAddr) {
        $ips[] = $normalizedRemoteAddr;
      }
    }
    $ips = array_values(array_unique($ips));
    $trusted_proxies = $this->get_settings("trustedProxies", array());
    if (!empty($trusted_proxies) && !$this->match_ip($normalizedRemoteAddr, $trusted_proxies)) {
      // Not a trusted proxy, use remote address as fallback
      $ips = array($normalizedRemoteAddr);
    }
    if (empty($ips)) {
      self::$ip_address = "127.0.0.1";
    }
    self::$ip_address = apply_filters("altcha_get_ip_address", $ips[0]);
    return self::$ip_address;
  }

  public function get_ip_address_hash(): string|null
  {
    $ip = $this->get_ip_address();
    if (empty($ip)) {
      return null;
    }
    return substr(hash("sha256", $this->anonymize_ip($ip)), 0, 16);
  }

  public function get_ip_country($ip = null): string|null
  {
    if (!empty(self::$ip_country)) {
      return self::$ip_country;
    }
    self::$ip_country = apply_filters("altcha_get_ip_country", null, $ip);
    return self::$ip_country;
  }

  public function get_license(): string|null
  {
    return get_option(self::$option_license);
  }

  public function get_referrer(): string|null
  {
    if (!isset($_SERVER["HTTP_REFERER"])) {
      return null;
    }
    return filter_var(sanitize_url(wp_unslash($_SERVER["HTTP_REFERER"])), FILTER_SANITIZE_URL);
  }

  public function get_request_body(): string
  {
    $body = file_get_contents("php://input", false, null, 0, self::$max_body_size);
    if (empty($body)) {
      return json_encode($_POST, JSON_PRETTY_PRINT);
    }
    return $body;
  }

  public function get_request_path(): string
  {
    $uri = isset($_SERVER["REQUEST_URI"]) ? sanitize_url(wp_unslash($_SERVER["REQUEST_URI"])) : "/";
    $uri = strtok($uri, "?");
    return $this->normalize_path($uri);
  }

  public function get_secret(): string
  {
    return trim(get_option(self::$option_secret));
  }

  public function get_settings($field = null, $default_value = null)
  {
    if (!$this->settings_json) {
      $settings_json = json_decode(get_option(self::$option_settings, "{}"), true);
      $this->settings_json = apply_filters("altcha_get_settings", $settings_json);
    }
    if ($field) {
      return isset($this->settings_json[$field]) ? $this->settings_json[$field] : $default_value;
    }
    return $this->settings_json;
  }

  public function get_site_path(): string
  {
    $path = wp_parse_url(site_url(), PHP_URL_PATH);
    if (empty($path)) {
      return "/";
    }
    return $path;
  }

  public function get_time_range($time): array
  {
    $now = time();
    $end = $now;
    // Check if input is in custom date range format (YYYY-MM-DD,YYYY-MM-DD)
    if (preg_match("/^\d{4}-\d{2}-\d{2},\d{4}-\d{2}-\d{2}$/", $time)) {
      list($startDate, $endDate) = explode(",", $time);
      $start = strtotime($startDate);
      $end = strtotime($endDate . " 23:59:59"); // End of the end date
      return array(
        "start" => (int) $start,
        "end" => (int) $end
      );
    }
    switch ($time) {
      case "last_24_hours":
        $start = strtotime("-24 hours", $now);
        break;
      case "last_7_days":
        $start = strtotime("-7 days", $now);
        break;
      case "last_30_days":
        $start = strtotime("-30 days", $now);
        break;
      case "last_90_days":
        $start = strtotime("-90 days", $now);
        break;
      default:
        $start = strtotime("-24 hours", $now);
        break;
    }
    return array(
      "start" => (int) $start,
      "end" => (int) $end
    );
  }

  public function get_time_start_end($time, $tz_offset = null): array
  {
    if ($tz_offset === null) {
      $tz_offset = $this->get_timezone_offset();
    }
    $time_range = $this->get_time_range($time);
    $interval = $time === "last_24_hours" ? HOUR_IN_SECONDS : DAY_IN_SECONDS;
    $start = $this->round_time($time_range["start"], $tz_offset, $interval);
    $end = max($time_range["end"], $this->round_time($time_range["end"], $tz_offset, $interval) + ($interval - 1));
    return array(
      "interval" => $interval,
      "start" => (int) $start,
      "end" => (int) $end
    );
  }

  public function get_timezone_country(string $timezone): string|null
  {
    if (empty($timezone)) {
      return null;
    }
    return self::$timezones[$timezone] ?? null;
  }

  public function get_timezone_offset(): int
  {
    $timezone_string = get_option("timezone_string");
    $gmt_offset_seconds = get_option("gmt_offset") * HOUR_IN_SECONDS;
    // If a timezone string is set (e.g., "Europe/Paris"), it overrides a manual offset.
    // We need to calculate the offset for a specific timestamp for accuracy.
    if ($timezone_string) {
      $timezone = new DateTimeZone($timezone_string);
      $date_time = new DateTime("now", $timezone);
      $gmt_offset_seconds = $timezone->getOffset($date_time);
    }
    return $gmt_offset_seconds;
  }

  public function get_transient_key(string $key): string
  {
    return substr(self::$transient_prefix . $key, 0, 172);
  }

  public function get_under_attack(): int
  {
    return intval(get_option(self::$option_under_attack));
  }

  public function get_under_attack_cookie_expire(): int
  {
    $threat_level = $this->get_under_attack();
    if ($threat_level) {
      // Expiration time between 2m..10m, gets lower with threat level
      return max(120_000, floor(600_000 / $threat_level));
    }
    return 0;
  }

  public function get_widget_attrs(array $attrs = array()): array
  {
    $attrs = array_merge(
      array(
        "challengeurl" => $this->get_challenge_url(),
        "customfetch" => "altchaCustomFetch",
      ),
      $attrs,
    );
    return apply_filters("altcha_get_widget_attrs", $attrs);
  }

  public function get_widget_html($attrs, $wrap = false)
  {
    altcha_enqueue_widget_scripts();
    $attributes = join(" ", array_map(function ($key) use ($attrs) {
      if (is_bool($attrs[$key])) {
        return $attrs[$key] ? $key : "";
      }
      if (preg_match("/^\d+$/", $key) && !empty($attrs[$key])) {
        return $attrs[$key];
      }
      return esc_attr($key) . "=\"" . esc_attr($attrs[$key]) . "\"";
    }, array_keys($attrs)));
    $html =
      "<altcha-widget "
      . $attributes
      . "></altcha-widget>"
      . "<noscript>"
      . "<div class=\"altcha-no-javascript\">This page requires JavaScript!</div>"
      . "</noscript>";
    if ($wrap) {
      $html = "<div class=\"altcha-widget-wrap\">" . $html . "</div>";
    }
    return apply_filters("altcha_get_widget_html", $html, $attrs, $wrap);
  }

  public function normalize_ip(string $ip): string|null
  {
    if (strpos($ip, "::ffff:") === 0) {
      $ipv4Part = substr($ip, 7); // Remove "::ffff:" prefix
      if (filter_var($ipv4Part, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
        return $ipv4Part;
      }
    }
    if (filter_var($ip, FILTER_VALIDATE_IP)) {
      return $ip;
    }
    return null;
  }

  public function random_secret(): string
  {
    return bin2hex(random_bytes(12));
  }

  public function generate_challenge(string|null $hmac_key = null, string|int|null $complexity = null, int|null $expires = null, array $params = array()): array
  {
    if ($hmac_key === null) {
      $hmac_key = $this->get_secret();
    }
    if ($complexity === null) {
      $complexity = "low";
    }
    if ($expires === null) {
      $expires = $this->get_settings("challengeExpiration");
      if (empty($expires)) {
        $expires = 300; // seconds
      }
    }
    $salt = $this->random_secret();
    $salt = $salt . "?" . http_build_query(array_merge(array(
      "expires" => time() + (int) $expires
    ), $params));
    // Add a delimiter to prevent parameter splicing
    if (!str_ends_with($salt, "&")) {
      $salt .= "&";
    }
    $range = $this->get_complexity($complexity);
    $secret_number = random_int($range["min"], $range["max"]);
    $challenge = hash("sha256", $salt . $secret_number);
    $signature = hash_hmac("sha256", $challenge, $hmac_key);
    return array(
      "algorithm" => "SHA-256",
      "challenge" => $challenge,
      "maxnumber" => $range["max"],
      "salt" => $salt,
      "signature" => $signature
    );
  }

  public function is_plugin_installed(string $filename)
  {
    $all_plugins = get_plugins();
    if (strpos($filename, "/") !== false) {
      return array_key_exists($filename, $all_plugins);
    } else {
      foreach ($all_plugins as $plugin_file => $plugin_data) {
        if (basename($plugin_file) === $filename) {
          return true;
        }
      }
      return false;
    }
  }

  public function log_event(string $event, string|null $action = null, string|null $form_id = null, string|null $reason = null, string|null $timezone = null, bool|null $interceptor = null, string|null $plugin = null, array|null $verification_data = null)
  {
    $settings = $this->get_settings();
    $analytics_enabled = isset($settings["eventsEnabled"]) && $settings["eventsEnabled"] === true;
    $log_challenges = isset($settings["eventsLogChallenges"]) && $settings["eventsLogChallenges"] === true;
    $log_blocked = isset($settings["eventsLogBlocked"]) && $settings["eventsLogBlocked"] === true;
    $log_failed_body = isset($settings["eventsLogFailedBody"]) && $settings["eventsLogFailedBody"] === true;
    $anonymize_ips = isset($settings["eventsAnonymizeIps"]) && $settings["eventsAnonymizeIps"] === true;
    if ($analytics_enabled && ($event !== "challenge" || $log_challenges) && ($event !== "blocked" || $log_blocked)) {
      global $wpdb;
      $table_name = $wpdb->prefix . self::$table_events;
      $ip = $this->get_ip_address();
      $referrer = $this->get_referrer();
      $country = null;
      if ($anonymize_ips && $ip) {
        $ip = $this->anonymize_ip($ip);
      }
      if ($timezone) {
        $ip_country = $this->get_ip_country();
        $country = $ip_country ?? $this->get_timezone_country($timezone);
      }
      $user_agent = isset($_SERVER["HTTP_USER_AGENT"]) ? substr(sanitize_text_field(wp_unslash($_SERVER["HTTP_USER_AGENT"])), 0, 255) : "";
      $body = $log_failed_body && ($event === "failed" || $event === "bot") ? $this->get_request_body() : null;
      $wpdb->insert(
        $table_name,
        array(
          "timestamp"   => current_time("mysql"),
          "timestamp_u" => time(),
          "action"      => !empty($action) ? sanitize_text_field(substr($action, 0, 100)) : null,
          "body"        => !empty($body) ? sanitize_textarea_field(substr($body, 0, self::$max_body_size)) : null,
          "country"     => !empty($country) ? sanitize_text_field(strtoupper($country)) : null,
          "event"       => sanitize_text_field(substr($event, 0, 20)),
          "form_id"     => !empty($form_id) ? sanitize_text_field(substr($form_id, 0, 100)) : null,
          "interceptor" => $interceptor === true ? 1 : 0,
          "plugin"      => !empty($plugin) ? sanitize_text_field(substr($plugin, 0, 100)) : null,
          "reason"      => !empty($reason) ? sanitize_text_field(substr($reason, 0, 100)) : null,
          "referrer"    => !empty($referrer) ? sanitize_text_field(substr($referrer, 0, 255)) : null,
          "timezone"    => !empty($timezone) ? sanitize_text_field(substr($timezone, 0, 100)) : null,
          "url"         => sanitize_text_field(substr($this->get_current_url(), 0, 255)),
          "user_id"     => get_current_user_id(),
          "ip_address"  => !empty($ip) ? sanitize_text_field(substr($ip, 0, 45)) : "",
          "user_agent"  => $user_agent,
          "verification_data" => !empty($verification_data) ? json_encode($verification_data) : null,
        ),
        array("%s", "%d", "%s", "%s", "%s", "%s", "%d", "%s", "%s", "%s", "%s", "%s", "%d", "%s", "%s", "%s"),
      );
    }
  }

  public function make_request(string $method, string $url, string|null $body = null)
  {
    $response = null;
    if ($method === "POST") {
      $response = wp_remote_post($url, array(
        "body" => $body,
        "headers" => array(
          "Content-Type" => "application/json",
        ),
      ));
    } else {
      $response = wp_remote_get($url);
    }
    if (is_wp_error($response)) {
      return array(
        "body" => null,
        "statusCode" => 0,
      );
    }
    $headers = wp_remote_retrieve_headers($response);
    $status_code = wp_remote_retrieve_response_code($response);
    return array(
      "body" => wp_remote_retrieve_body($response),
      "headers" => (array) $headers,
      "statusCode" => $status_code,
    );
  }

  public function match_cookies(array $cookie_pairs): bool
  {
    foreach ($cookie_pairs as $pair) {
      list($pair_name, $pair_value) = explode("=", $pair, 2);
      $pair_name = trim($pair_name);
      $pair_value = trim($pair_value);
      if (isset($_COOKIE[$pair_name]) && sanitize_text_field(wp_unslash($_COOKIE[$pair_name])) === $pair_value) {
        return true;
      }
    }
    return false;
  }

  public function match_ip(string $ip, array $cidrs_or_ips): bool
  {
    if (empty($ip)) {
      return false;
    }
    foreach ($cidrs_or_ips as $cidr_or_ip) {
      if ($ip === $cidr_or_ip) {
        return true;
      }
      if (str_contains($cidr_or_ip, "/") && $this->match_ip_cidr($ip, $cidr_or_ip)) {
        return true;
      }
    }
    return false;
  }

  public function match_ip_cidr(string $ip, string $cidr): bool
  {
    try {
      list($subnet, $mask) = explode("/", $cidr);
      $mask = (int)$mask;
      $ip_long = ip2long($ip);
      $subnet_long = ip2long($subnet);
      if ($ip_long === false || $subnet_long === false) {
        return false;
      }
      $network = $subnet_long & (~((1 << (32 - $mask)) - 1));
      $broadcast = $subnet_long | ((1 << (32 - $mask)) - 1);
      return ($ip_long >= $network) && ($ip_long <= $broadcast);
    } catch (Exception $e) {
      // Parsing error
    }
    return false;
  }

  public function match_patterns(string $str, array $patterns): bool
  {
    $matched = false;
    foreach ($patterns as $pattern) {
      $isNegation = str_starts_with($pattern, '!');
      $cleanPattern = $isNegation ? substr($pattern, 1) : $pattern;
      if ($cleanPattern === "*") {
        $matched = true;
      } else {
        $regex = '/^' . str_replace('\*', '.*', preg_quote($cleanPattern, '/')) . '$/i';
        if (preg_match($regex, $str)) {
          if ($isNegation) {
            return false;
          }
          return true;
        }
      }
    }
    return $matched;
  }

  public function normalize_path(string $uri): string
  {
    $site_path = wp_parse_url(site_url(), PHP_URL_PATH);
    if ($site_path && strpos($uri, $site_path) === 0) {
      $uri = substr($uri, strlen($site_path));
    }
    $uri = preg_replace("#^/?index\.php#", "", $uri);
    if ($uri === "" || $uri[0] !== "/") {
      $uri = "/" . $uri;
    }
    return $uri;
  }

  public function normalize_verification_data(array $verification_data, array $namespaces = array(
    "device",
    "email",
    "ip",
    "location",
    "params",
    "text",
  ))
  {
    foreach ($verification_data as $key => $value) {
      foreach ($namespaces as $ns) {
        $prefix = $ns . "_";
        if (str_starts_with($key, $prefix)) {
          if (!isset($verification_data[$ns])) {
            $verification_data[$ns] = array();
          }
          $verification_data[$ns][substr($key, strlen($prefix))] = $value;
          unset($verification_data[$key]);
        }
      }
    }
    return $verification_data;
  }

  public function parse_payload(string $payload, array &$params): array|null
  {
    $data = null;
    try {
      $data = json_decode(base64_decode($payload), true);
      if (isset($data["salt"])) {
        $salt_url = wp_parse_url($data["salt"]);
        if (isset($salt_url["query"]) && !empty($salt_url["query"])) {
          parse_str($salt_url["query"], $params);
        }
      }
    } catch (Exception $e) {
      // Parsing error
    }
    if (!empty($data) && isset($data["verificationData"])) {
      try {
        $verification_data_url = wp_parse_url("data?" . $data["verificationData"]);
        $verification_data = array();
        if (isset($verification_data_url["query"]) && !empty($verification_data_url["query"])) {
          parse_str($verification_data_url["query"], $verification_data);
        }
        $verification_data = $params["verification_data"] = $this->normalize_verification_data($verification_data);
        if (isset($verification_data["params"])) {
          foreach ($verification_data["params"] as $key => $value) {
            $params["params_" . $key] = $value;
          }
        }
        if (isset($verification_data["location"]) && isset($verification_data["location"]["timeZone"])) {
          $params["tz"] = $verification_data["location"]["timeZone"];
        }
      } catch (Exception $e) {
        // Parsing error
      }
    }
    return $data;
  }

  public function parse_rate_limit(string $limit): array|null
  {
    if (!preg_match("/^(\d+)\/(\d+)([smhd])$/", $limit, $matches)) {
      return null;
    }
    $limit = (int) $matches[1];
    $intervalValue = (int) $matches[2];
    $intervalUnit = $matches[3];
    // Convert interval to seconds
    $intervalInSeconds = match ($intervalUnit) {
      "s" => $intervalValue,           // seconds
      "m" => $intervalValue * 60,      // minutes
      "h" => $intervalValue * 3600,    // hours
      "d" => $intervalValue * 86400,   // days
      default => null,
    };
    if ($intervalInSeconds === null) {
      return null;
    }
    return [
      "limit" => $limit,
      "interval" => $intervalInSeconds,
      "unit" => $intervalUnit,
      "value" => $intervalValue
    ];
  }

  public function rate_limit(int $limit, int $interval_seconds, string|null $key = null): array
  {
    if ($key === null) {
      $key = $this->get_ip_address();
    }
    $transient_key = $this->get_transient_key("rt_" . $limit . "_" . $interval_seconds . sha1($key));
    $data = get_transient($transient_key);
    if ($data === false) {
      // first request in this interval
      $data = array(
        "count" => 1,
        "reset_time" => time() + $interval_seconds
      );
      set_transient($transient_key, $data, $interval_seconds);
      return array(
        "data" => $data,
        "exceeded" => false,
        "remaining" => $limit - 1,
        "reset_in" => $interval_seconds,
      );
    }
    $ttl = $data["reset_time"] - time();
    if ($data["count"] >= $limit) {
      // limit exceeded
      return array(
        "data" => $data,
        "exceeded" => true,
        "remaining" => 0,
        "reset_in" => $ttl,
      );
    }
    // increment count
    $data["count"]++;
    set_transient($transient_key, $data, $ttl);

    return array(
      "data" => $data,
      "exceeded" => false,
      "remaining" => $limit - $data["count"],
      "reset_in" => $ttl,
    );
  }

  public function round_time(int $time, int $gmt_offset_seconds, int $interval, bool $down = true): int
  {
    $local_timestamp = $time + $gmt_offset_seconds;
    return $down ? floor($local_timestamp / $interval) * $interval : ceil($local_timestamp / $interval) * $interval;
  }

  public function store_used_challenge(string $challenge)
  {
    set_transient($this->get_transient_key("ch_" . $challenge), "1", HOUR_IN_SECONDS);
  }

  public function verify(array|string|null $payload, array|null &$params = null, string|null $hmac_key = null, bool $store_used = true): bool
  {
    if ($hmac_key === null) {
      $hmac_key = $this->get_secret();
    }
    if (empty($payload) || empty($hmac_key)) {
      $this->verified = false;
      $this->verification_data = null;

      do_action("altcha_verify_result", false);

      return false;
    }

    $data = null;
    $result = false;
    $challenge_id = null;

    if (is_string($payload)) {
      $data = $this->parse_payload($payload, $params);
    } else if (is_array($payload)) {
      $data = $payload;
    }

    if (isset($data) && isset($data["verificationData"])) {
      $challenge_id = isset($data["id"]) ? $data["id"] : (isset($data["challenge"]) ? $data["challenge"] : null);
      if (empty($challenge_id) || $this->check_used_challenge($challenge_id) !== true) {
        $mode = $this->get_settings("mode");
        if ($mode === "sentinel") {
          $result = $this->verify_sentinel_signature($data, $params);
        } else {
          $result = $this->verify_server_signature($data, $params, $hmac_key);
        }
      }
    } else if (isset($data) && isset($data["challenge"])) {
      $challenge_id = $data["challenge"];
      if ($this->check_used_challenge($challenge_id) !== true) {
        $result = $this->verify_solution($data, $params, $hmac_key);
      }
    }

    if ($challenge_id && $store_used) {
      $this->store_used_challenge($challenge_id);
    }

    $this->verified = $result;
    $this->verification_data = isset($params["verification_data"]) ? $params["verification_data"] : null;

    do_action("altcha_verify_result", $result);

    return $result;
  }

  public function verify_server_signature(array $data, array $params, string|null $hmac_key = null): bool
  {
    if ($hmac_key === null) {
      $hmac_key = $this->get_secret();
    }
    $alg_ok = ($data["algorithm"] === "SHA-256");
    $calculated_hash = hash("sha256", $data["verificationData"], true);
    $calculated_signature = hash_hmac("sha256", $calculated_hash, $hmac_key);
    $signature_ok = ($data["signature"] === $calculated_signature);
    $verified = ($alg_ok && $signature_ok);
    return $verified;
  }

  public function verify_sentinel_signature(array $data, array &$params): bool
  {
    $url = $this->get_settings("sentinelUrl");
    $resp = $this->make_request("POST", str_replace("/v1/challenge", "/v1/verify/signature", $url), json_encode(array(
      "payload" => $data
    )));
    if ($resp["statusCode"] === 200) {
      $json = null;
      try {
        $json = json_decode((string) $resp["body"], true);
      } catch (Exception $e) {
        // Parsing error
      }
      if ($json && isset($json["verified"]) && $json["verified"] === true) {
        return true;
      }
    }
    return false;
  }

  public function verify_solution(array $data, array $params, string|null $hmac_key = null): bool
  {
    if ($hmac_key === null) {
      $hmac_key = $this->get_secret();
    }
    if (!empty($params["expires"])) {
      $expires = intval($params["expires"], 10);
      if ($expires > 0 && $expires < time()) {
        return false;
      }
    }
    $alg_ok = ($data["algorithm"] === "SHA-256");
    $calculated_challenge = hash("sha256", $data["salt"] . $data["number"]);
    $challenge_ok = ($data["challenge"] === $calculated_challenge);
    $calculated_signature = hash_hmac("sha256", $data["challenge"], $hmac_key);
    $signature_ok = ($data["signature"] === $calculated_signature);
    $verified = ($alg_ok && $challenge_ok && $signature_ok);
    return $verified;
  }
}
