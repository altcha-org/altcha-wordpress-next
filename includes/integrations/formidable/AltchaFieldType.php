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

if (!defined('ABSPATH')) exit;

if (class_exists("FrmFieldType")) {
	/** @disregard P1009 Undefined type */
	class AltchaFieldType extends FrmFieldType
	{
		protected $type = 'altcha';

		protected $has_input = true;

		protected function field_settings_for_type()
		{
			/** @disregard P1009 Undefined type */
			$settings            = parent::field_settings_for_type();
			$settings['default'] = true;

			return $settings;
		}

		protected function extra_field_opts()
		{
			return array();
		}

		protected function include_form_builder_file()
		{
			return dirname(__FILE__) . '/builder-field.php';
		}

		public function displayed_field_type($field)
		{
			return array(
				$this->type => true,
			);
		}

		public function show_extra_field_choices($args)
		{
			include(dirname(__FILE__) . '/builder-settings.php');
		}

		protected function html5_input_type()
		{
			return 'text';
		}

		public function validate($args)
		{
			return array();
		}

		public function front_field_input($args, $shortcode_atts)
		{
			$plugin = AltchaPlugin::$instance;
			return wp_kses(
				"<div style=\"flex-basis:100%\">" .
					$plugin->get_widget_html(
						$plugin->get_widget_attrs(array(
							"challengeurl" => $plugin->get_challenge_url(array(
								"params.plugin" => "formidable",
								"params.form_id" => isset($args["form"]) ? $args["form"]->id : "",
							))
						)),
					) . "</div>",
				AltchaPlugin::$html_espace_allowed_tags,
			);
		}
	}
}
