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

add_action("elementor/init", "altcha_elementor_init");
add_action("elementor_pro/forms/fields/register", "altcha_elementor_form_field");

function altcha_elementor_init() {
  altcha_enqueue_widget_scripts();
}

function altcha_elementor_form_field($form_fields_registrar)
{
  require_once(__DIR__ . "/elementor/field.php");

  $form_fields_registrar->register(new \Elementor_Form_Altcha_Field());
}
