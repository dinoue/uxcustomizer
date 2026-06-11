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
     * Asset itemtypes that receive this tab. Each must:
     *   1. Exist as a class in the running GLPI install.
     *   2. Be present in ImpactMap::knownItemtypes() so the ajax scope
     *      whitelist accepts it.
     * Adding more itemtypes here is the only step needed to expand coverage.
     */
    public const ITEMTYPES = ['Computer', 'Appliance'];

    /**
     * ITIL itemtypes that receive this tab (SysAid-style "business impact in
     * the ticket"). The map is seeded by ALL assets linked to the object —
     * a technician sees the blast radius without leaving the ticket.
     */
    public const ITIL_ITEMTYPES = ['Ticket', 'Change', 'Problem'];

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
        $class = get_class($item);
        if (!in_array($class, self::ITEMTYPES, true)
            && !in_array($class, self::ITIL_ITEMTYPES, true)) {
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
        $itemtype = get_class($item);
        $isItil   = in_array($itemtype, self::ITIL_ITEMTYPES, true);
        if (!$isItil && !in_array($itemtype, self::ITEMTYPES, true)) {
            return false;
        }
        if (!Config::isModuleEnabled('impactmap')) {
            return false;
        }

        $rootDoc = $CFG_GLPI['root_doc'] ?? '';
        $itemId  = (int) $item->getID();

        // Cache-busted asset URL builder (file mtime → fall back to plugin version).
        $asset = static function (string $rel) use ($rootDoc): string {
            $v = @filemtime(__DIR__ . '/../' . $rel) ?: PLUGIN_UXCUSTOMIZER_VERSION;
            return $rootDoc . '/plugins/uxcustomizer/' . $rel . '?v=' . $v;
        };

        // The dataUrl carries only the scope identity; the JS appends
        // &forward=&backward= from the depth selects on each fetch.
        // ITIL scope: the SERVER resolves the linked assets (rights-checked).
        $dataUrl = $rootDoc . '/plugins/uxcustomizer/ajax/impactmap.php'
            . ($isItil
                ? '?itil_itemtype=' . urlencode($itemtype) . '&itil_items_id=' . $itemId
                : '?itemtype=' . urlencode($itemtype) . '&items_id=' . $itemId);

        // Default BFS depths (forward = arrows out / impacts; backward = arrows in / impacted by).
        $defaultForward  = 2;
        $defaultBackward = 2;

        // ── Stylesheet (idempotent: GLPI/browser dedupe by URL) ────────────
        echo '<link rel="stylesheet" type="text/css" href="' . $asset('public/css/impactmap.css') . '">';

        echo '<div class="container-fluid mt-2 uxc-impact-itemscope">';
        echo '<div class="d-flex align-items-center mb-2">';
        echo '<i class="ti ti-affiliate me-2" style="font-size:1.5rem"></i>';
        echo '<h3 class="m-0">' . __('Impact Map', 'uxcustomizer') . '</h3>';
        echo '<span class="text-muted ms-3 small">'
            . ($isItil
                ? __('Combined neighborhood of every asset linked to this object. Seed assets are emphasized.', 'uxcustomizer')
                : __('Subgraph connected to this asset. Native GLPI Impact Analysis is the source of truth.', 'uxcustomizer'))
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
        echo '<button type="button" class="btn btn-sm btn-outline-secondary uxc-impact-export"'
            . ' title="' . htmlspecialchars(__('Download the current view as a PNG image', 'uxcustomizer'), ENT_QUOTES, 'UTF-8') . '">'
            . '<i class="ti ti-photo-down me-1"></i>' . __('Export PNG', 'uxcustomizer') . '</button>';
        // Layout mode. "Flow" = dagre LR, the same layered look as GLPI's
        // native Impact Analysis — default here so the tab feels familiar.
        echo '<div class="d-inline-flex align-items-center ms-1">';
        echo '<label class="form-label small mb-0 me-1" for="uxc-impact-layoutsel">' . __('Layout', 'uxcustomizer') . '</label>';
        echo '<select id="uxc-impact-layoutsel" class="form-select form-select-sm uxc-impact-layoutsel" style="width:auto">';
        echo '<option value="flow" selected>' . __('Flow (left-right)', 'uxcustomizer') . '</option>';
        echo '<option value="force">' . __('Force (organic)', 'uxcustomizer') . '</option>';
        echo '<option value="tree">' . __('Tree (top-down)', 'uxcustomizer') . '</option>';
        echo '</select>';
        echo '</div>';

        // ── BFS depth selectors (on-asset tab only; the config-page Impact
        // Map keeps the org-wide view with no depth limit). 1..5 covers the
        // useful range; 2/2 matches GLPI native impact analysis density. ──
        $depthRange = [1, 2, 3, 4, 5];
        echo '<div class="uxc-impact-depth d-inline-flex align-items-center ms-2">';
        echo '<label class="form-label small mb-0 me-1" for="uxc-impact-forward" title="'
            . htmlspecialchars(__('Hops along arrows OUT (what this asset impacts)', 'uxcustomizer'), ENT_QUOTES, 'UTF-8')
            . '">' . __('Forward', 'uxcustomizer') . '</label>';
        echo '<select id="uxc-impact-forward" class="form-select form-select-sm uxc-impact-forward" style="width:auto">';
        foreach ($depthRange as $d) {
            $sel = ($d === $defaultForward) ? ' selected' : '';
            echo '<option value="' . $d . '"' . $sel . '>' . $d . '</option>';
        }
        echo '</select>';
        echo '<label class="form-label small mb-0 ms-2 me-1" for="uxc-impact-backward" title="'
            . htmlspecialchars(__('Hops along arrows IN (what impacts this asset)', 'uxcustomizer'), ENT_QUOTES, 'UTF-8')
            . '">' . __('Backward', 'uxcustomizer') . '</label>';
        echo '<select id="uxc-impact-backward" class="form-select form-select-sm uxc-impact-backward" style="width:auto">';
        foreach ($depthRange as $d) {
            $sel = ($d === $defaultBackward) ? ' selected' : '';
            echo '<option value="' . $d . '"' . $sel . '>' . $d . '</option>';
        }
        echo '</select>';
        echo '</div>';

        // Auto-group by type (iTop-style). Off by default here: depth-scoped
        // asset views are small; the org-wide config page defaults it on.
        echo '<label class="form-check form-switch d-inline-flex align-items-center mb-0 ms-2"'
            . ' title="' . htmlspecialchars(sprintf(__('Collapse loose nodes into one group per type when a type has more than %d nodes', 'uxcustomizer'), 8), ENT_QUOTES, 'UTF-8') . '">';
        echo '<input class="form-check-input uxc-impact-autogroup" type="checkbox">';
        echo '<span class="form-check-label small ms-1">' . __('Auto-group types', 'uxcustomizer') . '</span>';
        echo '</label>';

        echo '<span class="uxc-impact-status"></span>';
        echo '</div>';

        echo '<div class="uxc-impact-legend"></div>';

        // Stage (canvas + side panel)
        echo '<div class="uxc-impact-stage">';
        echo '<div class="uxc-impact-canvas"></div>';
        echo '<aside class="uxc-impact-side" aria-live="polite"></aside>';
        echo '<div class="uxc-impact-empty" style="display:none"><div>'
            . '<i class="ti ti-affiliate-off mb-2" style="font-size:2rem"></i><br>'
            . ($isItil
                ? __('No mappable assets are linked to this object (see its Items tab).', 'uxcustomizer')
                : __('No impact relations linked to this asset. Use the native Impact Analysis tab to start linking items.', 'uxcustomizer'))
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
        // dagre powers the "Flow" (left-right layered) layout — the same
        // algorithm GLPI's native Impact Analysis uses. Bundled locally.
        echo '<script src="' . $asset('public/js/dagre.min.js') . '"></script>';
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
            'forward'      => __('Forward', 'uxcustomizer'),
            'backward'     => __('Backward', 'uxcustomizer'),
            'health'       => __('Health', 'uxcustomizer'),
            'health_ok'    => __('good', 'uxcustomizer'),
            'health_warn'  => __('warning', 'uxcustomizer'),
            'health_crit'  => __('critical', 'uxcustomizer'),
            'open_tickets' => __('Open tickets', 'uxcustomizer'),
            'agent_seen'   => __('Agent seen', 'uxcustomizer'),
            'today'        => __('today', 'uxcustomizer'),
            'days_ago'     => __('days ago', 'uxcustomizer'),
            'type_group'   => __('Type group', 'uxcustomizer'),
        ];
    }
}
