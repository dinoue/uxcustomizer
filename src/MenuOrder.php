<?php

/**
 * UX Customizer - Menu Order module
 *
 * Reorders GLPI's left navigation menu per profile via the redefine_menus hook.
 * Ported from the standalone `taborder` plugin. See
 * ../GLPI-Shared/rules/glpi-conventions.md § "Altering the GLPI menu".
 *
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Uxcustomizer;

class MenuOrder
{
    private const TABLE = 'glpi_plugin_uxcustomizer_menuorders';

    /**
     * Saved menu key order for a profile, or null if none saved.
     *
     * @return string[]|null
     */
    public static function getOrder(int $profileId): ?array
    {
        global $DB;

        $it = $DB->request([
            'SELECT' => ['menu_order'],
            'FROM'   => self::TABLE,
            'WHERE'  => ['profiles_id' => $profileId],
            'LIMIT'  => 1,
        ]);

        if (count($it) === 0) {
            return null;
        }
        $decoded = json_decode($it->current()['menu_order'], true);
        return is_array($decoded) ? $decoded : null;
    }

    /**
     * Upsert the saved order for a profile. `date_mod` is column-managed.
     *
     * @param string[] $order
     */
    public static function saveOrder(int $profileId, array $order): bool
    {
        global $DB;

        $payload = ['menu_order' => json_encode(array_values($order), JSON_UNESCAPED_UNICODE)];

        $exists = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::TABLE,
            'WHERE'  => ['profiles_id' => $profileId],
            'LIMIT'  => 1,
        ]);

        if (count($exists) > 0) {
            return (bool) $DB->update(self::TABLE, $payload, ['profiles_id' => $profileId]);
        }
        $payload['profiles_id'] = $profileId;
        return (bool) $DB->insert(self::TABLE, $payload);
    }

    /** Delete the saved order for a profile (reset to GLPI default). */
    public static function resetOrder(int $profileId): bool
    {
        global $DB;
        return (bool) $DB->delete(self::TABLE, ['profiles_id' => $profileId]);
    }

    /**
     * Reorder a GLPI menu array to the active profile's saved order. Called from
     * the redefine_menus hook; GLPI renders from the returned array (order =
     * display order). Unsaved keys are appended (new plugins go to the bottom).
     */
    public static function redefineMenus(array $menu): array
    {
        $profileId = (int) ($_SESSION['glpiactiveprofile']['id'] ?? 0);
        if ($profileId === 0) {
            return $menu;
        }

        $saved = self::getOrder($profileId);
        if ($saved === null) {
            return $menu;
        }

        $reordered = [];
        foreach ($saved as $key) {
            if (array_key_exists($key, $menu)) {
                $reordered[$key] = $menu[$key];
            }
        }
        foreach ($menu as $key => $value) {
            if (!array_key_exists($key, $reordered)) {
                $reordered[$key] = $value;
            }
        }
        return $reordered;
    }

    /**
     * Top-level menu keys as they currently appear in the session, for the
     * config UI. Html::header() has populated $_SESSION['glpimenu'] by the time
     * a front/ page body renders.
     *
     * @return string[]
     */
    public static function getCurrentMenuKeys(): array
    {
        return array_keys($_SESSION['glpimenu'] ?? []);
    }

    /** Human-readable title for a top-level menu key. */
    public static function getMenuTitle(string $key): string
    {
        $title = $_SESSION['glpimenu'][$key]['title'] ?? null;
        if (is_string($title) && $title !== '') {
            return $title;
        }
        return ucwords(str_replace(['_', '-'], ' ', $key));
    }
}
