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

if (is_plugin_active("formidable/formidable.php")) {

  add_action("plugins_loaded", "altcha_load_formidable_field");
  add_filter("frm_get_field_type_class", "altcha_get_field_type_class", 10, 2);
  add_filter("frm_available_fields", "altcha_add_new_field");

  function altcha_load_formidable_field()
  {
    spl_autoload_register("altcha_forms_autoloader");
  }

  function altcha_forms_autoloader($class_name)
  {
    if (! preg_match("/^Altcha.+$/", $class_name)) {
      return;
    }

    $filepath = dirname(__FILE__);
    $filepath .= "/formidable/" . $class_name . ".php";

    if (file_exists($filepath)) {
      require($filepath);
    }
  }

  function altcha_get_field_type_class($class, $field_type)
  {
    if ($field_type === "altcha") {
      $class = "AltchaFieldType";
    }
    return $class;
  }

  function altcha_add_new_field($fields)
  {
    $fields["altcha"] = array(
      "name" => "ALTCHA",
      "icon" => "frm_icon_font frm_shield_check_icon",
    );
    return $fields;
  }
}
