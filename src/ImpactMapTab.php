<?php

/**
 * UX Customizer - Impact Map item tab
 *
 * Adds an additional "Impact Map" tab to the form pages of certain itemtypes
 * (Computer, Appliance — see ITEMTYPES). The tab renders the same vis-network
 * topology view as the Setup → UX Customizer config page, but scoped to the
 * subgraph connected to the current asset (BFS from this item over impact
 * relations).
 *
 * Coexists with GLPI's native "Impact Analysis" tab — this one adds the
 * enhanced UX (collapsible compounds, edge counts, filter pills, tree layout,
 * search dim) without touching the native page.
 *
 * Registered in setup.php via:
 *   Plugin::registerClass(ImpactMapTab::class, ['addtabon' => ['Computer', 'Appliance']]);
 *
 * NB: vis-network and impactmap.js are emitted INLINE in the tab response so
 * they load only when the tab is shown. GLPI 11's tab AJAX loader extracts and
 * executes embedded scripts (jQuery-style), which is how the existing
 * ComputerDashboard tab loads its stylesheet too.
 *
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Uxcustomizer;

use CommonGLPI;

class ImpactMapTab extends CommonGLPI
{
    /**
     * Itemtypes that receive this tab. Each must:
     *   1. Exist as a class in the running GLPI install.
     *   2. Be present in ImpactMap::knownItemtypes() so the ajax scope
     *      whitelist accepts it.
     * Adding more itemtypes here is the only step needed to expand coverage.
     */
    public const ITEMTYPES = ['Computer', 'Appliance'];

    public static function getTypeName($nb = 0): string
    {
        return __('Impact Map', 'uxcustomizer');
    }

    /**
     * Tab label. Empty string skips the tab — used to hide it when the
     * item is brand-new (no id), the module is off, or the itemtype isn't
     * covered (the addtabon registration already filters by itemtype, but we
     * double-check defensively).
     */
    public function getTabNameForItem(CommonGLPI $item, $withtemplate = 0)
    {
        if (!method_exists($item, 'isNewItem') || $item->isNewItem()) {
            return '';
        }
        if (!in_array(get_class($item), self::ITEMTYPES, true)) {
            return '';
        }
        if (!Config::isModuleEnabled('impactmap')) {
            return '';
        }
        return self::createTabEntry(
            __('Impact Map', 'uxcustomizer'),
            0,
            $item->getType(),
            'ti ti-affiliate'
        );
    }

    /**
     * Render the tab body: the same DOM the config-page Impact Map tab uses,
     * plus the JS bootstrap. Scoped to the current asset via the dataUrl query
     * string (itemtype + items_id → BFS in ImpactMap::getGraph).
     */
    public static function displayTabContentForItem(CommonGLPI $item, $tabnum = 1, $withtemplate = 0): bool
    {
        global $CFG_GLPI;

        if (!method_exists($item, 'isNewItem') || $item->isNewItem()) {
            return false;
        }
        if (!in_array(get_class($item), self::ITEMTYPES, true)) {
            return false;
        }
        if (!Config::isModuleEnabled('impactmap')) {
            return false;
        }

        $rootDoc  = $CFG_GLPI['root_doc'] ?? '';
        $itemtype = get_class($item);
        $itemId   = (int) $item->getID();

        // Cache-busted asset URL builder (file mtime → fall back to plugin version).
        $asset = static function (string $rel) use ($rootDoc): string {
            $v = @filemtime(__DIR__ . '/../' . $rel) ?: PLUGIN_UXCUSTOMIZER_VERSION;
            return $rootDoc . '/plugins/uxcustomizer/' . $rel . '?v=' . $v;
        };

        $dataUrl = $rootDoc . '/plugins/uxcustomizer/ajax/impactmap.php'
            . '?itemtype=' . urlencode($itemtype)
            . '&items_id=' . $itemId;

        // ── Stylesheet (idempotent: GLPI/browser dedupe by URL) ────────────
        echo '<link rel="stylesheet" type="text/css" href="' . $asset('public/css/impactmap.css') . '">';

        echo '<div class="container-fluid mt-2 uxc-impact-itemscope">';
        echo '<div class="d-flex align-items-center mb-2">';
        echo '<i class="ti ti-affiliate me-2" style="font-size:1.5rem"></i>';
        echo '<h3 class="m-0">' . __('Impact Map', 'uxcustomizer') . '</h3>';
        echo '<span class="text-muted ms-3 small">'
            . __('Subgraph connected to this asset. Native GLPI Impact Analysis is the source of truth.', 'uxcustomizer')
            . '</span>';
        echo '</div>';

        // Wrapper div the client JS hooks into (the very same id used on the
        // config page — keeps the JS untouched).
        echo '<div class="uxc-impact-page" id="uxc-impact">';

        // Toolbar
        echo '<div class="uxc-impact-toolbar">';
        echo '<input type="text" class="form-control form-control-sm uxc-impact-search"'
            . ' placeholder="' . htmlspecialchars(__('Search node by name…', 'uxcustomizer'), ENT_QUOTES, 'UTF-8') . '">';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary uxc-impact-collapse-all">'
            . '<i class="ti ti-arrows-minimize me-1"></i>' . __('Collapse groups', 'uxcustomizer') . '</button>';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary uxc-impact-expand-all">'
            . '<i class="ti ti-arrows-maximize me-1"></i>' . __('Expand all', 'uxcustomizer') . '</button>';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary uxc-impact-fit">'
            . '<i class="ti ti-focus-2 me-1"></i>' . __('Fit', 'uxcustomizer') . '</button>';
        echo '<button type="button" class="btn btn-sm btn-outline-secondary uxc-impact-layout"'
            . ' title="' . htmlspecialchars(__('Toggle force-directed / tree layout', 'uxcustomizer'), ENT_QUOTES, 'UTF-8') . '">'
            . '<i class="ti ti-binary-tree me-1"></i>' . __('Tree layout', 'uxcustomizer') . '</button>';
        echo '<span class="uxc-impact-status"></span>';
        echo '</div>';

        echo '<div class="uxc-impact-legend"></div>';

        // Stage (canvas + side panel)
        echo '<div class="uxc-impact-stage">';
        echo '<div class="uxc-impact-canvas"></div>';
        echo '<aside class="uxc-impact-side" aria-live="polite"></aside>';
        echo '<div class="uxc-impact-empty" style="display:none"><div>'
            . '<i class="ti ti-affiliate-off mb-2" style="font-size:2rem"></i><br>'
            . __('No impact relations linked to this asset. Use the native Impact Analysis tab to start linking items.', 'uxcustomizer')
            . '</div></div>';
        echo '</div>';

        echo '</div>'; // uxc-impact-page
        echo '</div>'; // container

        // ── JS bootstrap (inline) ─────────────────────────────────────────
        echo '<script>window.UxcImpactConfig = ' . json_encode([
            'dataUrl' => $dataUrl,
            'i18n'    => self::i18nKeys(),
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) . ';</script>';
        echo '<script src="' . $asset('public/js/vis-network.min.js') . '"></script>';
        echo '<script src="' . $asset('public/js/impactmap.js') . '"></script>';

        return true;
    }

    /**
     * Translation keys the JS client looks up. Kept centralised so the config
     * page and the item tab stay in sync — change once, propagate to both.
     *
     * @return array<string,string>
     */
    private static function i18nKeys(): array
    {
        return [
            'loading'      => __('Loading…', 'uxcustomizer'),
            'failed'       => __('Failed to load:', 'uxcustomizer'),
            'nodes'        => __('nodes', 'uxcustomizer'),
            'relations'    => __('relations', 'uxcustomizer'),
            'truncated'    => __('truncated to ', 'uxcustomizer'),
            'type'         => __('Type', 'uxcustomizer'),
            'id'           => __('ID', 'uxcustomizer'),
            'group'        => __('Group', 'uxcustomizer'),
            'members'      => __('Members', 'uxcustomizer'),
            'expand'       => __('Expand', 'uxcustomizer'),
            'open_in_glpi' => __('Open in GLPI', 'uxcustomizer'),
            'conn'         => __('conn.', 'uxcustomizer'),
            'layout_tree'  => __('Tree layout', 'uxcustomizer'),
            'layout_force' => __('Force layout', 'uxcustomizer'),
            'show_type'    => __('Click to show', 'uxcustomizer'),
            'hide_type'    => __('Click to hide', 'uxcustomizer'),
        ];
    }
}
