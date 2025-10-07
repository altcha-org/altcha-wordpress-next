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

class AltchaPluginUpdater
{
  private $username = "altcha-org";
  private $repository = "altcha-wordpress-next";
  private $plugin_file = null;
  private $plugin_slug = null;
  private $authorize_token = null;

  public function __construct(string $plugin_file, string|null $token = null)
  {
    $this->plugin_file = $plugin_file;
    $this->plugin_slug = plugin_basename($plugin_file);
    $this->authorize_token = $token;
    add_filter("pre_set_site_transient_update_plugins", array($this, "check_github_updates"));
    add_filter("upgrader_post_install", array($this, "post_install"), 10, 3);
  }

  public function check_github_updates($transient)
  {
    if (empty($transient->checked)) {
      return $transient;
    }
    $github_data = $this->get_github_data();
    if (is_array($github_data)) {
      $new_version = preg_replace("/^v/", "", $github_data["tag_name"]);
      if ($github_data && version_compare($transient->checked[$this->plugin_slug], $new_version, "<")) {
        $plugin_data = array(
          "new_version" => $new_version,
          "url" => $github_data["html_url"],
          "package" => $github_data["zipball_url"]
        );

        if ($this->authorize_token) {
          $plugin_data["package"] = add_query_arg("access_token", $this->authorize_token, $plugin_data["package"]);
        }
        $obj = new stdClass();
        $obj->slug = dirname($this->plugin_slug);
        $obj->new_version = $plugin_data["new_version"];
        $obj->url = $plugin_data["url"];
        $obj->package = $plugin_data["package"];
        $obj->plugin = $this->plugin_slug;
        $transient->response[$this->plugin_slug] = $obj;
      }
    }
    return $transient;
  }

  private function get_github_data()
  {
    $url = "https://api.github.com/repos/{$this->username}/{$this->repository}/releases/latest";
    $args = array(
      "headers" => array(
        "User-Agent" => "WordPress-Plugin-Updater"
      )
    );
    if ($this->authorize_token) {
      $args["headers"]["Authorization"] = "token {$this->authorize_token}";
    }
    $response = wp_remote_get($url, $args);
    if (is_wp_error($response) || wp_remote_retrieve_response_code($response) !== 200) {
      return false;
    }
    return json_decode(wp_remote_retrieve_body($response), true);
  }

  public function post_install($response, $hook_extra, $result)
  {
    global $wp_filesystem;
    $install_directory = plugin_dir_path($this->plugin_file);
    $wp_filesystem->move($result["destination"], $install_directory);
    $result["destination"] = $install_directory;
    if (is_plugin_active($this->plugin_slug)) {
      activate_plugin($this->plugin_slug);
    }
    return $result;
  }
}
