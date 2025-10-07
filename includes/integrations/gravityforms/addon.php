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

/** @disregard P1009 Undefined type */
GFForms::include_addon_framework();

/** @disregard P1009 Undefined type */
class ALTCHA_GFFormsAddOn extends GFAddOn
{

    protected $_version = "2.0.0";
    protected $_min_gravityforms_version = "2.5";
    protected $_slug = "altcha";
    protected $_full_path = __FILE__;
    protected $_short_title = "ALTCHA";

    private static $_instance = null;

    public static function get_instance()
    {
        if (self::$_instance == null) {
            self::$_instance = new ALTCHA_GFFormsAddOn();
        }

        return self::$_instance;
    }

    public function get_menu_icon()
    {
        return "dashicons-superhero";
    }

    public function pre_init()
    {
        /** @disregard P1009 Undefined type */
        parent::pre_init();

        /** @disregard P1013 Undefined function */
        if ($this->is_gravityforms_supported() && class_exists("GF_Field")) {
            require_once(__DIR__ . "/field.php");
        }
    }

    public function init_admin()
    {
        /** @disregard P1009 Undefined type */
        parent::init_admin();
    }
}
