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

if (class_exists("GFAddOn")) {
  add_action(
    "gform_loaded",
    function () {
      require_once(__DIR__ . "/gravityforms/addon.php");
	    /** @disregard P1009 Undefined type */
      GFAddOn::register("ALTCHA_GFFormsAddOn");
    },
    5
  );

  add_filter(
    "gform_entry_is_spam",
    function ($is_spam, $form, $entry) {
      if ($is_spam) {
        return $is_spam;
      }

      $plugin = AltchaPlugin::$instance;
      if (isset($plugin->verification_data)) {
        $is_spam = isset($plugin->verification_data["classification"]) && $plugin->verification_data["classification"] === "BAD";

        if ($is_spam && method_exists("GFCommon", "set_spam_filter")) {
          if (isset($plugin->verification_data) && isset($plugin->verification_data["score"])) {
            $text = "";
            foreach ($plugin->verification_data as $key => $value) {
              $text .= "\r\n{$key}: {$value}";
            }
	          /** @disregard P1009 Undefined type */
            GFCommon::set_spam_filter(rgar($form, "id"), "ALTCHA Sentinel", $text);
          }
        }
      }

      return $is_spam;
    },
    10,
    3
  );
}
