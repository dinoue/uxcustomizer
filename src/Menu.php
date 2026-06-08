<?php

/**
 * UX Customizer - menu entry
 *
 * Provides a navigation entry under Setup (the 'config' top-level menu) that
 * links to the plugin's config page. Registered via
 * $PLUGIN_HOOKS['menu_toadd']['uxcustomizer'] = ['config' => Menu::class].
 *
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Uxcustomizer;

use CommonGLPI;
use Session;

class Menu extends CommonGLPI
{
    public static function getTypeName($nb = 0)
    {
        return __('UX Customizer', 'uxcustomizer');
    }

    public static function getMenuName()
    {
        return self::getTypeName();
    }

    public static function getIcon()
    {
        return 'ti ti-adjustments';
    }

    /**
     * Only super-admins (config UPDATE) see the entry; the page enforces the
     * same right server-side regardless.
     */
    public static function canView()
    {
        return Session::haveRight('config', UPDATE);
    }

    public static function canCreate()
    {
        return false;
    }

    /**
     * Menu definition consumed by GLPI when building the Setup menu.
     * 'page' is relative to $CFG_GLPI['root_doc'] (GLPI prepends it).
     */
    public static function getMenuContent()
    {
        if (!self::canView()) {
            return false;
        }

        return [
            'title' => self::getMenuName(),
            'page'  => '/plugins/uxcustomizer/front/config.php',
            'icon'  => self::getIcon(),
        ];
    }
}
