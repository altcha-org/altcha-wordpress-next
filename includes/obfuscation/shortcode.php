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

add_action("init", "altcha_register_obfuscate_shortcode");

function altcha_register_obfuscate_shortcode(): void
{
  add_shortcode("obfuscate", "altcha_obfuscate_shortcode_handler");
}

function altcha_obfuscate_shortcode_handler($atts, $content = null): string
{
  $atts = shortcode_atts(
    array(
      "class" => "",
      "email" => "",
      "obfuscated" => "",
      "label" => "",
      "tel" => "",
      "text" => "",
    ),
    $atts,
    "obfuscate"
  );

  $label = !empty($atts["label"]) ? $atts["label"] : "Click here";
  $class = !empty($atts["class"]) ? $atts["class"] : "";

  $content = "";
  $obfuscated = "";

  if (!empty($atts["obfuscated"])) {
    $obfuscated = esc_attr(trim($atts["obfuscated"]));
  } elseif (!empty($atts["text"])) {
    $content = trim($atts["text"]);
  } elseif (!empty($atts["email"])) {
    $content = trim($atts["email"]);
    if (strpos($content, "mailto:") !== 0) {
      $content = "mailto:" . $content;
    }
  } elseif (!empty($atts["tel"])) {
    $content = trim($atts["tel"]);
    if (strpos($content, "tel:") !== 0) {
      $content = "tel:" . $content;
    }
  }

  if (empty($obfuscated) && !empty($content)) {
    $obfuscated = AltchaObfuscation::obfuscate($content);
  }

  if (empty($obfuscated)) {
    return "";
  }

  if (function_exists("altcha_enqueue_obfuscation_scripts")) {
    altcha_enqueue_obfuscation_scripts();
  }

  if (function_exists("altcha_enqueue_widget_scripts")) {
    altcha_enqueue_widget_scripts();
  }

  return "<altcha-widget " .
    "obfuscated=\"" . esc_attr($obfuscated) . "\" " .
    "floating >" .
    "<button class=\"altcha-obfuscation-button " . esc_attr($class) . "\">" . esc_attr($label) . "</button>" .
    "</altcha-widget>";
}
