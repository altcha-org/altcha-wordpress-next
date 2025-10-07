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

if ( ! defined( "ABSPATH" ) ) exit;

/** @disregard P1009 Undefined type */
class ALTCHA_GFForms_Field extends GF_Field
{

	public $type = "altcha";

	public function get_form_editor_field_title()
	{
		return "ALTCHA";
	}

	public function get_form_editor_button()
	{
		return array(
			"group" => "advanced_fields",
			"text"  => $this->get_form_editor_field_title(),
		);
	}

	function get_form_editor_field_settings()
	{
		return array(
			"label_setting",
			"description_setting",
			"label_placement_setting",
			"error_message_setting"
		);
	}

	public function get_form_editor_field_icon()
	{
		return "dashicons-superhero";
	}

	public function is_conditional_logic_supported()
	{
		return true;
	}

	public function get_field_input($form, $value = "", $entry = null)
	{
		$plugin = AltchaPlugin::$instance;
    /** @disregard P1013 Undefined function */
		if ($this->is_form_editor()) {
			$widget_html = "<div style=\"display:flex;gap:1rem;border: 1px solid lightgray;max-width:260px;padding: 1em;border-radius:4px;font-size:80%\">"
				. "<div><span class=\"dashicons-before dashicons-superhero\"></span></div>"
				. "<div><span>ALTCHA will be displayed here.</span></div>"
				. "</div>";
		} else {
			$widget_html = wp_kses(
				"<div style=\"flex-basis:100%\">" .
					$plugin->get_widget_html(
						$plugin->get_widget_attrs(array(
							"challengeurl" => $plugin->get_challenge_url(array(
								"params.plugin" => "gravityforms",
								"params.form_id" => isset($form["id"]) ? $form["id"] : "",
							))
						)),
					) . "</div>",
				AltchaPlugin::$html_espace_allowed_tags,
			);
		}
		return sprintf("<div class=\"ginput_container ginput_container_%s gfield--type-html\">%s</div>", $this->type, $widget_html);
	}
}

/** @disregard P1009 Undefined type */
GF_Fields::register(new ALTCHA_GFForms_Field());
