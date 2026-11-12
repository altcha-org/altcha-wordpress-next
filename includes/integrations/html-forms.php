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

if (is_plugin_active("html-forms/html-forms.php")) {
  // Expand only [altcha] in the admin-authored form template. Priority 5 runs
  // before HTML Forms' hf_template (priority 10) substitutes visitor-controlled
  // values such as {{url_params.*}}, so those never reach a shortcode parser.
  add_filter(
    "hf_form_markup",
    "altcha_html_forms_render_shortcode",
    5
  );
}

function altcha_html_forms_render_shortcode($markup)
{
  if (!is_string($markup) || strpos($markup, "[altcha") === false) {
    return $markup;
  }
  $pattern = get_shortcode_regex(array("altcha"));
  return preg_replace_callback("/$pattern/", "do_shortcode_tag", $markup);
}
