<?php

/**
 * UX Customizer - Tab Order module
 *
 * Reorders the tabs on asset detail pages (Computer, Printer, …). GLPI 11 has
 * NO server hook to reorder item-form tabs (verified in CommonGLPI/Ajax), so
 * the actual reordering is done client-side by public/js/tabreorder.js. This
 * class is the server side: enumerate an itemtype's tabs for the config UI, and
 * persist a global per-itemtype order.
 *
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Uxcustomizer;

class TabOrder
{
    private const TABLE = 'glpi_plugin_uxcustomizer_taborders';

    /**
     * Asset itemtypes whose tabs can be reordered. Keyed by the form-page slug
     * (basename of *.form.php, lowercased) → GLPI class. The slug lets the
     * client JS map the current page to an itemtype.
     */
    public const ITEMTYPES = [
        'computer'         => \Computer::class,
        'monitor'          => \Monitor::class,
        'networkequipment' => \NetworkEquipment::class,
        'peripheral'       => \Peripheral::class,
        'phone'            => \Phone::class,
        'printer'          => \Printer::class,
        'software'         => \Software::class,
        'rack'             => \Rack::class,
        'enclosure'        => \Enclosure::class,
        'pdu'              => \PDU::class,
        'cluster'          => \Cluster::class,
    ];

    /** Resolve a slug or class name to a supported GLPI class, or null. */
    public static function resolveItemtype(string $value): ?string
    {
        $value = trim($value);
        if (isset(self::ITEMTYPES[strtolower($value)])) {
            return self::ITEMTYPES[strtolower($value)];
        }
        // Also accept the class name directly (e.g. "NetworkEquipment").
        foreach (self::ITEMTYPES as $class) {
            if (strcasecmp($class, $value) === 0) {
                return $class;
            }
        }
        return null;
    }

    /**
     * Enumerate an itemtype's tabs as [tabKey => plainLabel], in GLPI's native
     * order. Loads a representative existing item (lowest id) so conditional
     * tabs appear; falls back to a blank item.
     *
     * @return array<string,string>
     */
    public static function getTabs(string $class): array
    {
        if (!class_exists($class) || !is_subclass_of($class, \CommonGLPI::class)) {
            return [];
        }

        /** @var \CommonGLPI $item */
        $item = new $class();

        // Try to load the lowest-id real record so all conditional tabs show.
        if ($item instanceof \CommonDBTM) {
            global $DB;
            $table = $item->getTable();
            $loaded = false;
            foreach ($DB->request(['SELECT' => ['id'], 'FROM' => $table, 'ORDER' => ['id ASC'], 'LIMIT' => 1]) as $row) {
                $loaded = $item->getFromDB((int) $row['id']);
            }
            if (!$loaded) {
                $item->getEmpty();
            }
        }

        if (!method_exists($item, 'defineAllTabs')) {
            return [];
        }

        // defineAllTabs() asks every registered tab for its name. Some tab-name
        // renderers (core or plugin) emit warnings when invoked outside the
        // normal item-display flow — e.g. a Twig `call()` that resolves to an
        // invalid callable. Those warnings are harmless for our purpose (we only
        // need the tab KEYS), so we suppress non-fatal errors and swallow any
        // throwable for the duration of the call, then restore handling.
        set_error_handler(static function () {
            return true; // swallow notices/warnings raised during enumeration
        });
        try {
            $raw = $item->defineAllTabs();
        } catch (\Throwable $e) {
            $raw = [];
        } finally {
            restore_error_handler();
        }
        if (!is_array($raw)) {
            $raw = [];
        }

        $tabs = [];
        foreach ($raw as $key => $label) {
            if ($key === 'no_all_tab') {
                continue;
            }
            // Labels are HTML (icons/badges). Reduce to readable text.
            $text = trim(html_entity_decode(strip_tags((string) $label), ENT_QUOTES, 'UTF-8'));
            $text = preg_replace('/\s+/', ' ', $text);
            $tabs[$key] = $text !== '' ? $text : $key;
        }
        return $tabs;
    }

    /**
     * Display order for the config UI: saved keys first (that still exist), then
     * any new/unsaved tabs appended. Returns [tabKey => label].
     *
     * @return array<string,string>
     */
    public static function getDisplayTabs(string $class): array
    {
        $tabs  = self::getTabs($class);
        $saved = self::getOrder($class);
        if ($saved === null) {
            return $tabs;
        }
        $ordered = [];
        foreach ($saved as $key) {
            if (array_key_exists($key, $tabs)) {
                $ordered[$key] = $tabs[$key];
            }
        }
        foreach ($tabs as $key => $label) {
            if (!array_key_exists($key, $ordered)) {
                $ordered[$key] = $label;
            }
        }
        return $ordered;
    }

    /**
     * Saved settings for an itemtype: ['order' => string[], 'hidden' => string[]].
     * Empty arrays when nothing is saved.
     *
     * @return array{order:string[],hidden:string[]}
     */
    public static function getSettings(string $class): array
    {
        global $DB;
        $it = $DB->request([
            'SELECT' => ['tab_order', 'hidden_tabs'],
            'FROM'   => self::TABLE,
            'WHERE'  => ['itemtype' => $class],
            'LIMIT'  => 1,
        ]);
        if (count($it) === 0) {
            return ['order' => [], 'hidden' => []];
        }
        $row    = $it->current();
        $order  = json_decode((string) $row['tab_order'], true);
        $hidden = json_decode((string) ($row['hidden_tabs'] ?? ''), true);
        return [
            'order'  => is_array($order) ? $order : [],
            'hidden' => is_array($hidden) ? $hidden : [],
        ];
    }

    /**
     * Saved tab-key order for an itemtype, or null if none saved.
     * (Kept for the redefine-style apply read.)
     *
     * @return string[]|null
     */
    public static function getOrder(string $class): ?array
    {
        $order = self::getSettings($class)['order'];
        return $order === [] ? null : $order;
    }

    /**
     * Upsert the global tab order + hidden set for an itemtype.
     *
     * @param string[] $order
     * @param string[] $hidden
     */
    public static function saveSettings(string $class, array $order, array $hidden): bool
    {
        global $DB;
        $payload = [
            'tab_order'   => json_encode(array_values($order), JSON_UNESCAPED_UNICODE),
            'hidden_tabs' => json_encode(array_values($hidden), JSON_UNESCAPED_UNICODE),
        ];

        $exists = $DB->request([
            'SELECT' => ['id'],
            'FROM'   => self::TABLE,
            'WHERE'  => ['itemtype' => $class],
            'LIMIT'  => 1,
        ]);
        if (count($exists) > 0) {
            return (bool) $DB->update(self::TABLE, $payload, ['itemtype' => $class]);
        }
        $payload['itemtype'] = $class;
        return (bool) $DB->insert(self::TABLE, $payload);
    }

    /** Delete the saved order for an itemtype (reset to GLPI default). */
    public static function resetOrder(string $class): bool
    {
        global $DB;
        return (bool) $DB->delete(self::TABLE, ['itemtype' => $class]);
    }
}
