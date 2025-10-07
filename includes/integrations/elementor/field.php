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

if (!class_exists("\ElementorPro\Modules\Forms\Fields\Field_Base")) {
  die();
}

/** @disregard P1009 Undefined type */
class Elementor_Form_Altcha_Field extends \ElementorPro\Modules\Forms\Fields\Field_Base
{
  public function get_type()
  {
    return "altcha";
  }

  public function get_name()
  {
    return esc_html("ALTCHA");
  }

  public function render($item, $item_index, $form)
  {
    $plugin = AltchaPlugin::$instance;
    echo wp_kses(
      "<div style=\"flex-basis:100%\">" .
        $plugin->get_widget_html(
					$plugin->get_widget_attrs(array(
						"challengeurl" => $plugin->get_challenge_url(array(
							"params.plugin" => "elementor-pro",
							"params.form_id" => $form->get_id(),
						))
					)),
        ) . "</div>",
      AltchaPlugin::$html_espace_allowed_tags,
    );
    // shadow element for error reporting
		echo wp_kses("<input type=\"hidden\" " . $form->get_render_attribute_string("input" . $item_index) . ">", AltchaPlugin::$html_espace_allowed_tags);
  }

  public function update_controls($widget)
	{
	  /** @disregard P1009 Undefined type */
		$elementor = \ElementorPro\Plugin::elementor();
		$control_data = $elementor->controls_manager->get_control_from_stack($widget->get_unique_name(), "form_fields");
		if (is_wp_error($control_data)) {
			return;
		}
		$control_data = $this->remove_control_form_field_type("required", $control_data);
		$widget->update_control("form_fields", $control_data);
	}

  private function remove_control_form_field_type($control_name, $control_data)
	{
		foreach ($control_data["fields"] as $index => $field) {
			if ($control_name !== $field["name"]) {
				continue;
			}
			foreach ($field["conditions"]["terms"] as $condition_index => $terms) {
				if (!isset($terms["name"]) || "field_type" !== $terms["name"] || !isset($terms["operator"]) || "!in" !== $terms["operator"]) {
					continue;
				}
				$control_data["fields"][$index]["conditions"]["terms"][$condition_index]["value"][] = $this->get_type();
				break;
			}
			break;
		}
		return $control_data;
	}

  public function validation($field, $record, $ajax_handler)
  {
  }
}
