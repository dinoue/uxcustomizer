<?php

/**
 * UX Customizer - Impact Map data layer
 *
 * Reads GLPI 11's native impact-analysis tables and returns a graph
 * (nodes, edges, compounds) suitable for rendering with vis-network on
 * the client. Read-only: this never writes to GLPI's impact tables.
 *
 * Native GLPI tables consumed:
 *   - glpi_impactrelations  edges: itemtype_source/items_id_source -> itemtype_impacted/items_id_impacted
 *   - glpi_impactitems      per-node persistence (compound membership, positions)
 *   - glpi_impactcompounds  named groups: id, name, color
 *
 * Returns plain arrays of scalars; everything user-visible is HTML-escaped
 * at the consumer side (vis-network titles use HTML option).
 *
 * @license   GPL-3.0-or-later
 */

namespace GlpiPlugin\Uxcustomizer;

class ImpactMap
{
    /**
     * Hard cap on returned nodes — protects the browser and the DB from
     * accidentally rendering a 5000-node org map. The UI surfaces a
     * "truncated" notice when this kicks in.
     */
    public const MAX_NODES = 750;

    /**
     * Itemtype -> visual identity (Faddom-style colored nodes).
     * Tabler glyph names (rendered next to the label in the side panel, not
     * inside the node — vis-network doesn't render Tabler glyphs natively).
     */
    private const ITEMTYPE_STYLE = [
        'Computer'         => ['color' => '#4C9BE8', 'border' => '#2563EB', 'icon' => 'ti ti-device-desktop',     'label' => 'Computer'],
        'Monitor'          => ['color' => '#38B6FF', 'border' => '#0EA5E9', 'icon' => 'ti ti-device-tv',          'label' => 'Monitor'],
        'NetworkEquipment' => ['color' => '#5BA85F', 'border' => '#16A34A', 'icon' => 'ti ti-network',            'label' => 'Network equipment'],
        'Printer'          => ['color' => '#94A3B8', 'border' => '#64748B', 'icon' => 'ti ti-printer',            'label' => 'Printer'],
        'Peripheral'       => ['color' => '#A78BFA', 'border' => '#8B5CF6', 'icon' => 'ti ti-device-usb',         'label' => 'Peripheral'],
        'Phone'            => ['color' => '#F472B6', 'border' => '#DB2777', 'icon' => 'ti ti-device-mobile',      'label' => 'Phone'],
        'Software'         => ['color' => '#7B5EA7', 'border' => '#6D28D9', 'icon' => 'ti ti-box',                'label' => 'Software'],
        'DatabaseInstance' => ['color' => '#C9A227', 'border' => '#B45309', 'icon' => 'ti ti-database',           'label' => 'Database'],
        'Database'         => ['color' => '#C9A227', 'border' => '#B45309', 'icon' => 'ti ti-database',           'label' => 'Database'],
        'Cluster'          => ['color' => '#DC2626', 'border' => '#991B1B', 'icon' => 'ti ti-stack-2',            'label' => 'Cluster'],
        'Domain'           => ['color' => '#14B8A6', 'border' => '#0F766E', 'icon' => 'ti ti-world',              'label' => 'Domain'],
        'Appliance'        => ['color' => '#6366F1', 'border' => '#4338CA', 'icon' => 'ti ti-server',             'label' => 'Appliance'],
        'PluginAppliancesAppliance' => ['color' => '#6366F1', 'border' => '#4338CA', 'icon' => 'ti ti-server',   'label' => 'Appliance'],
        'Rack'             => ['color' => '#0EA5E9', 'border' => '#0369A1', 'icon' => 'ti ti-server-2',           'label' => 'Rack'],
        'Enclosure'        => ['color' => '#3B82F6', 'border' => '#1D4ED8', 'icon' => 'ti ti-box',                'label' => 'Enclosure'],
        'PDU'              => ['color' => '#22C55E', 'border' => '#15803D', 'icon' => 'ti ti-plug',               'label' => 'PDU'],
    ];

    private const DEFAULT_STYLE = ['color' => '#9CA3AF', 'border' => '#6B7280', 'icon' => 'ti ti-package', 'label' => 'Item'];

    /**
     * Visual identity for an itemtype (color/border/icon/label).
     *
     * @return array{color:string,border:string,icon:string,label:string}
     */
    public static function styleFor(string $itemtype): array
    {
        return self::ITEMTYPE_STYLE[$itemtype] ?? self::DEFAULT_STYLE;
    }

    /**
     * Known itemtypes (for filter dropdowns). Stable, hand-curated list.
     *
     * @return array<string,string>  classname => display label
     */
    public static function knownItemtypes(): array
    {
        $out = [];
        foreach (self::ITEMTYPE_STYLE as $cls => $style) {
            $out[$cls] = $style['label'];
        }
        return $out;
    }

    /**
     * Build the impact graph from GLPI's native tables.
     *
     * @param array{
     *   itemtype?:string,items_id?:int,
     *   seeds?:list<array{itemtype:string,items_id:int}>,
     *   forward?:int,backward?:int,
     *   types?:string[]
     * } $scope
     *        Optional scoping. If itemtype+items_id given, returns ONLY the
     *        subgraph reachable from that node by a BOUNDED DIRECTED BFS:
     *        `forward` hops along arrows OUT (impacts), `backward` hops
     *        along arrows IN (impacted by). Both default to 2, capped at 10.
     *        If the start node has no relations, returns an empty graph.
     *        If types given, additionally filters nodes by itemtype.
     *
     * @return array{
     *   nodes: list<array{
     *     id:string, itemtype:string, items_id:int, name:string,
     *     color:string, border:string, icon:string, group:string,
     *     compoundId:?int, url:string, title:string
     *   }>,
     *   edges: list<array{from:string, to:string}>,
     *   compounds: list<array{id:int, name:string, color:string, count:int}>,
     *   meta: array{truncated:bool, total_nodes:int, total_edges:int}
     * }
     */
    public static function getGraph(array $scope = []): array
    {
        global $DB, $CFG_GLPI;

        $empty = [
            'nodes'     => [],
            'edges'     => [],
            'compounds' => [],
            'meta'      => ['truncated' => false, 'total_nodes' => 0, 'total_edges' => 0],
        ];

        if (!$DB->tableExists('glpi_impactrelations')) {
            return $empty;
        }

        // ── 1. Read all relations ────────────────────────────────────────────
        $rows = [];
        try {
            foreach ($DB->request(['FROM' => 'glpi_impactrelations']) as $r) {
                $rows[] = [
                    'fs' => (string) $r['itemtype_source'],
                    'fi' => (int)    $r['items_id_source'],
                    'ts' => (string) $r['itemtype_impacted'],
                    'ti' => (int)    $r['items_id_impacted'],
                ];
            }
        } catch (\Throwable $e) {
            return $empty;
        }

        // ── 2. Collect unique nodes ──────────────────────────────────────────
        $nodeSet = []; // 'Type:id' => ['itemtype'=>, 'items_id'=>]
        foreach ($rows as $r) {
            $kf = $r['fs'] . ':' . $r['fi'];
            $kt = $r['ts'] . ':' . $r['ti'];
            $nodeSet[$kf] = ['itemtype' => $r['fs'], 'items_id' => $r['fi']];
            $nodeSet[$kt] = ['itemtype' => $r['ts'], 'items_id' => $r['ti']];
        }

        // Also include isolated nodes that appear in glpi_impactitems but have
        // no relations — they're still part of the saved impact graph.
        if ($DB->tableExists('glpi_impactitems')) {
            try {
                foreach ($DB->request([
                    'SELECT' => ['itemtype', 'items_id'],
                    'FROM'   => 'glpi_impactitems',
                ]) as $r) {
                    $k = $r['itemtype'] . ':' . (int) $r['items_id'];
                    if (!isset($nodeSet[$k])) {
                        $nodeSet[$k] = [
                            'itemtype' => (string) $r['itemtype'],
                            'items_id' => (int) $r['items_id'],
                        ];
                    }
                }
            } catch (\Throwable $e) {
                // Non-fatal — proceed without isolated nodes.
            }
        }

        // ── 3. Optional scope: BOUNDED DIRECTED BFS from one or more seeds ──
        // Seeds come either from a single (itemtype, items_id) pair or from
        // scope['seeds'] (e.g. all assets linked to a Ticket). Walks `forward`
        // hops along arrows OUT (impacts) and `backward` hops along arrows IN
        // (impacted by), independently, from EVERY seed. Defaults match GLPI's
        // native impact analysis depth (≈2 each). Seeds absent from the impact
        // data are injected as isolated nodes (a ticket asset without
        // relations should still show on the triage map), and a scope that
        // matches nothing returns an empty graph — never the global one.
        $seeds = [];
        if (!empty($scope['seeds']) && is_array($scope['seeds'])) {
            foreach ($scope['seeds'] as $s) {
                if (!empty($s['itemtype']) && !empty($s['items_id'])) {
                    $seeds[(string) $s['itemtype'] . ':' . (int) $s['items_id']] = [
                        'itemtype' => (string) $s['itemtype'],
                        'items_id' => (int) $s['items_id'],
                    ];
                }
            }
        } elseif (!empty($scope['itemtype']) && !empty($scope['items_id'])) {
            $key = $scope['itemtype'] . ':' . (int) $scope['items_id'];
            $seeds[$key] = [
                'itemtype' => (string) $scope['itemtype'],
                'items_id' => (int) $scope['items_id'],
            ];
        }

        if ($seeds !== []) {
            $forwardDepth  = isset($scope['forward'])  ? max(0, min(10, (int) $scope['forward']))  : 2;
            $backwardDepth = isset($scope['backward']) ? max(0, min(10, (int) $scope['backward'])) : 2;

            // Inject seeds missing from the impact data as isolated nodes.
            foreach ($seeds as $key => $s) {
                if (!isset($nodeSet[$key])) {
                    $nodeSet[$key] = $s;
                }
            }

            // Directed adjacency: forward edges = source → impacted.
            $forward = []; // a => [b, …]  (a impacts b)
            $reverse = []; // b => [a, …]  (a impacts b → b is impacted by a)
            foreach ($rows as $r) {
                $a = $r['fs'] . ':' . $r['fi'];
                $b = $r['ts'] . ':' . $r['ti'];
                $forward[$a][$b] = true;
                $reverse[$b][$a] = true;
            }

            $seedKeys = array_keys($seeds);
            $keep = array_fill_keys($seedKeys, true);

            // Forward BFS (seeds → what they impact).
            $layer = $seedKeys;
            for ($d = 0; $d < $forwardDepth && $layer; $d++) {
                $next = [];
                foreach ($layer as $cur) {
                    foreach (array_keys($forward[$cur] ?? []) as $nb) {
                        if (!isset($keep[$nb])) {
                            $keep[$nb] = true;
                            $next[] = $nb;
                        }
                    }
                }
                $layer = $next;
            }

            // Backward BFS (what impacts the seeds → upstream).
            $layer = $seedKeys;
            for ($d = 0; $d < $backwardDepth && $layer; $d++) {
                $next = [];
                foreach ($layer as $cur) {
                    foreach (array_keys($reverse[$cur] ?? []) as $nb) {
                        if (!isset($keep[$nb])) {
                            $keep[$nb] = true;
                            $next[] = $nb;
                        }
                    }
                }
                $layer = $next;
            }

            $nodeSet = array_intersect_key($nodeSet, $keep);
        }

        // ── 4. Filter by itemtype set (if requested) ─────────────────────────
        if (!empty($scope['types']) && is_array($scope['types'])) {
            $allow = array_flip($scope['types']);
            $nodeSet = array_filter(
                $nodeSet,
                static fn(array $n): bool => isset($allow[$n['itemtype']])
            );
        }

        // ── 4b. Entity-access filtering (SEC-1) ──────────────────────────────
        // Drop any node the current session may not see. Applies to EVERY
        // scope (org-wide, asset, ITIL): the raw impact tables aren't entity
        // scoped by GLPI, and 2.0.0 lets non-super-admins reach this code.
        if ($nodeSet !== []) {
            $entByType = [];
            foreach ($nodeSet as $n) {
                $entByType[$n['itemtype']][] = $n['items_id'];
            }
            $allowedEnt = self::filterByEntity($entByType);
            $nodeSet = array_filter(
                $nodeSet,
                static fn(array $n): bool => isset($allowedEnt[$n['itemtype']][$n['items_id']])
            );
        }

        $totalNodes = count($nodeSet);
        $totalEdges = count($rows);

        // ── 5. Cap to MAX_NODES ──────────────────────────────────────────────
        $truncated = false;
        if (count($nodeSet) > self::MAX_NODES) {
            $nodeSet = array_slice($nodeSet, 0, self::MAX_NODES, true);
            $truncated = true;
        }

        // ── 6. Batch-resolve names per itemtype ──────────────────────────────
        $byType = [];
        foreach ($nodeSet as $key => $n) {
            $byType[$n['itemtype']][] = $n['items_id'];
        }
        $names = self::resolveNames($byType);

        // ── 6b. Health signals (batched — never per-node queries) ───────────
        $ticketCounts = self::openTicketCounts($byType); // null if source unavailable
        $agentDays    = self::agentStaleness($byType);   // itemtype => id => days

        // ── 7. Read compound memberships ─────────────────────────────────────
        $memberCompound = []; // 'Type:id' => parent_compound_id
        if ($DB->tableExists('glpi_impactitems')) {
            try {
                foreach ($DB->request([
                    'SELECT' => ['itemtype', 'items_id', 'parent_id'],
                    'FROM'   => 'glpi_impactitems',
                    'WHERE'  => ['parent_id' => ['>', 0]],
                ]) as $r) {
                    $k = $r['itemtype'] . ':' . (int) $r['items_id'];
                    if (isset($nodeSet[$k])) {
                        $memberCompound[$k] = (int) $r['parent_id'];
                    }
                }
            } catch (\Throwable $e) {
                // Ignore.
            }
        }

        // ── 8. Read compound metadata ────────────────────────────────────────
        $compoundIds = array_unique(array_values($memberCompound));
        $compounds   = [];
        if ($compoundIds && $DB->tableExists('glpi_impactcompounds')) {
            $countByCompound = array_count_values($memberCompound);
            try {
                foreach ($DB->request([
                    'FROM'  => 'glpi_impactcompounds',
                    'WHERE' => ['id' => $compoundIds],
                ]) as $r) {
                    $id = (int) $r['id'];
                    $compounds[] = [
                        'id'    => $id,
                        'name'  => (string) ($r['name'] ?? ('Group ' . $id)),
                        'color' => self::normalizeColor($r['color'] ?? '#6B7280'),
                        'count' => $countByCompound[$id] ?? 0,
                    ];
                }
            } catch (\Throwable $e) {
                // Ignore.
            }
        }

        // ── 9. Build node payload ────────────────────────────────────────────
        $root  = $CFG_GLPI['root_doc'] ?? '';
        $nodes = [];
        foreach ($nodeSet as $key => $n) {
            $style    = self::styleFor($n['itemtype']);
            $name     = $names[$n['itemtype']][$n['items_id']] ?? ('#' . $n['items_id']);
            $compound = $memberCompound[$key] ?? null;
            $url      = $root . '/front/' . $n['itemtype'] . '.form.php?id=' . $n['items_id'];

            // Health: combine open-ticket and agent-staleness signals. A node
            // with no signal at all gets level=null (no overlay, no noise).
            $tickets = ($ticketCounts === null)
                ? null
                : (int) ($ticketCounts[$n['itemtype']][$n['items_id']] ?? 0);
            $aDays   = $agentDays[$n['itemtype']][$n['items_id']] ?? null;
            $signals = 0;
            $issues  = 0;
            if ($tickets !== null) { $signals++; if ($tickets > 0) { $issues++; } }
            if ($aDays !== null)   { $signals++; if ($aDays > 2)   { $issues++; } }
            $level = $signals === 0 ? null : ($issues === 0 ? 'ok' : ($issues >= 2 ? 'crit' : 'warn'));

            // Tooltip — vis-network renders this as plain text by default; the
            // client opts into HTML rendering. All values HTML-escaped here.
            $tipExtra = '';
            if ($tickets !== null && $tickets > 0) {
                $tipExtra .= '<div class="uxc-impact-tip-sub">'
                    . sprintf(_n('%d open ticket', '%d open tickets', $tickets, 'uxcustomizer'), $tickets)
                    . '</div>';
            }
            if ($aDays !== null && $aDays > 2) {
                $tipExtra .= '<div class="uxc-impact-tip-sub">'
                    . sprintf(__('Agent silent for %d days', 'uxcustomizer'), $aDays)
                    . '</div>';
            }
            $title = '<div class="uxc-impact-tip">'
                . '<strong>' . htmlspecialchars($name, ENT_QUOTES, 'UTF-8') . '</strong>'
                . '<div class="uxc-impact-tip-sub">' . htmlspecialchars($style['label'], ENT_QUOTES, 'UTF-8') . '</div>'
                . $tipExtra
                . '</div>';

            $nodes[] = [
                'id'         => $key,
                'itemtype'   => $n['itemtype'],
                'items_id'   => $n['items_id'],
                'name'       => $name,
                'label'      => $name,
                'color'      => $style['color'],
                'border'     => $style['border'],
                'icon'       => $style['icon'],
                'group'      => $n['itemtype'],
                'compoundId' => $compound,
                'url'        => $url,
                'title'      => $title,
                'seed'       => isset($seeds[$key]),
                'health'     => [
                    'level'      => $level,
                    'tickets'    => $tickets,
                    'agent_days' => $aDays,
                ],
            ];
        }

        // ── 10. Build edge payload (only edges with BOTH endpoints kept) ─────
        $edges = [];
        foreach ($rows as $r) {
            $a = $r['fs'] . ':' . $r['fi'];
            $b = $r['ts'] . ':' . $r['ti'];
            if (isset($nodeSet[$a]) && isset($nodeSet[$b])) {
                $edges[] = ['from' => $a, 'to' => $b];
            }
        }

        return [
            'nodes'     => $nodes,
            'edges'     => $edges,
            'compounds' => array_values($compounds),
            'meta'      => [
                'truncated'   => $truncated,
                'total_nodes' => $totalNodes,
                'total_edges' => $totalEdges,
                'max_nodes'   => self::MAX_NODES,
            ],
        ];
    }

    /**
     * Resolve the SQL table for an itemtype. The class owns this knowledge;
     * falling back to getTableForItemType keeps us correct for plugin types.
     * Returns null when the class/table can't be resolved.
     */
    private static function tableFor(string $itemtype): ?string
    {
        global $DB;

        if ($itemtype === '' || !class_exists($itemtype)) {
            return null;
        }
        try {
            $table = method_exists($itemtype, 'getTable')
                ? $itemtype::getTable()
                : (\getTableForItemType($itemtype) ?: null);
        } catch (\Throwable $e) {
            return null;
        }
        return ($table && $DB->tableExists($table)) ? $table : null;
    }

    /**
     * Entity-access guard (SEC-1). GLPI does NOT auto-scope the raw impact
     * tables, so the BFS neighborhood / ITIL seed expansion could otherwise
     * surface item names and health from entities the current session can't
     * see. For every itemtype we keep only the ids that pass GLPI's own
     * entity-restriction criteria for the session's active entities.
     *
     * Itemtypes whose table has no `entities_id` column are entity-agnostic
     * and pass through unchanged. On query error we fail CLOSED (drop the
     * ids) — better to under-show than to disclose across entities.
     *
     * @param array<string,int[]> $byType itemtype => ids present in the graph
     * @return array<string,array<int,bool>> itemtype => id => true (allowed)
     */
    private static function filterByEntity(array $byType): array
    {
        global $DB;

        $allowed = [];
        foreach ($byType as $itemtype => $ids) {
            if (!is_string($itemtype) || $itemtype === '' || $ids === []) {
                continue;
            }
            $ids   = array_values(array_unique(array_map('intval', $ids)));
            $table = self::tableFor($itemtype);

            // No resolvable table, or no entity concept → not entity-restricted.
            if ($table === null || !$DB->fieldExists($table, 'entities_id')) {
                foreach ($ids as $id) {
                    $allowed[$itemtype][$id] = true;
                }
                continue;
            }

            $crit = \getEntitiesRestrictCriteria($table, '', '', true);
            try {
                foreach ($DB->request([
                    'SELECT' => ['id'],
                    'FROM'   => $table,
                    'WHERE'  => array_merge(['id' => $ids], $crit),
                ]) as $row) {
                    $allowed[$itemtype][(int) $row['id']] = true;
                }
            } catch (\Throwable $e) {
                // Fail closed: prove access or don't disclose.
            }
        }
        return $allowed;
    }

    /**
     * Resolve display names in batches: one query per itemtype.
     *
     * @param array<string,int[]> $byType  itemtype => list of ids
     * @return array<string,array<int,string>>  itemtype => id => name
     */
    private static function resolveNames(array $byType): array
    {
        global $DB;

        $out = [];
        foreach ($byType as $itemtype => $ids) {
            if (!is_string($itemtype) || $itemtype === '' || $ids === []) {
                continue;
            }
            $ids = array_values(array_unique(array_map('intval', $ids)));

            $table = self::tableFor((string) $itemtype);
            if ($table === null) {
                continue;
            }

            // Use 'name' when present (the convention for most GLPI assets);
            // otherwise fall back to the row id.
            $hasName = $DB->fieldExists($table, 'name');
            $hasDel  = $DB->fieldExists($table, 'is_deleted');

            $select = ['id'];
            if ($hasName) {
                $select[] = 'name';
            }
            $where = ['id' => $ids];
            if ($hasDel) {
                // Show soft-deleted assets too — they still appear in the
                // impact graph until explicitly removed.
                // No is_deleted filter.
            }
            try {
                foreach ($DB->request([
                    'SELECT' => $select,
                    'FROM'   => $table,
                    'WHERE'  => $where,
                ]) as $row) {
                    $id = (int) $row['id'];
                    $out[$itemtype][$id] = $hasName && !empty($row['name'])
                        ? (string) $row['name']
                        : ($itemtype . ' #' . $id);
                }
            } catch (\Throwable $e) {
                // Non-fatal.
            }
        }
        return $out;
    }

    /** ITIL itemtype => its asset-link table + foreign key. */
    public const ITIL_LINK_TABLES = [
        'Ticket'  => ['table' => 'glpi_items_tickets',  'fk' => 'tickets_id'],
        'Change'  => ['table' => 'glpi_changes_items',  'fk' => 'changes_id'],
        'Problem' => ['table' => 'glpi_items_problems', 'fk' => 'problems_id'],
    ];

    /**
     * Assets linked to an ITIL object (Ticket / Change / Problem), as BFS
     * seeds. Itemtypes are whitelisted against knownItemtypes() — the same
     * filter the ajax endpoint applies to direct scope params.
     *
     * @return list<array{itemtype:string,items_id:int}>
     */
    public static function linkedAssetSeeds(string $itilType, int $id): array
    {
        global $DB;

        $map = self::ITIL_LINK_TABLES[$itilType] ?? null;
        if ($map === null || $id <= 0 || !$DB->tableExists($map['table'])) {
            return [];
        }

        $known = self::knownItemtypes();
        $seeds = [];
        try {
            foreach ($DB->request([
                'SELECT' => ['itemtype', 'items_id'],
                'FROM'   => $map['table'],
                'WHERE'  => [$map['fk'] => $id],
            ]) as $row) {
                if (isset($known[$row['itemtype']])) {
                    $seeds[] = [
                        'itemtype' => (string) $row['itemtype'],
                        'items_id' => (int) $row['items_id'],
                    ];
                }
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $seeds;
    }

    /**
     * Open-ticket counts per node, in ONE query (status 1-4 = new / assigned /
     * planned / waiting — same definition as the Computer Dashboard health).
     * Over-fetches by id (no per-tuple WHERE) and lets the caller filter via
     * the (itemtype, id) lookup; that's cheap and keeps the SQL portable.
     *
     * @param array<string,int[]> $byType itemtype => ids present in the graph
     * @return array<string,array<int,int>>|null itemtype => id => count, or
     *                                           null when sources are missing
     */
    private static function openTicketCounts(array $byType): ?array
    {
        global $DB;

        if ($byType === []
            || !$DB->tableExists('glpi_items_tickets')
            || !$DB->tableExists('glpi_tickets')) {
            return null;
        }

        $types  = array_keys($byType);
        $allIds = [];
        foreach ($byType as $ids) {
            foreach ($ids as $id) { $allIds[$id] = true; }
        }

        $out = [];
        try {
            foreach ($DB->request([
                'SELECT'     => [
                    'glpi_items_tickets.itemtype',
                    'glpi_items_tickets.items_id',
                    'COUNT' => 'glpi_items_tickets.id AS cnt',
                ],
                'FROM'       => 'glpi_items_tickets',
                'INNER JOIN' => ['glpi_tickets' => ['ON' => ['glpi_items_tickets' => 'tickets_id', 'glpi_tickets' => 'id']]],
                'WHERE'      => [
                    'glpi_items_tickets.itemtype' => $types,
                    'glpi_items_tickets.items_id' => array_keys($allIds),
                    'glpi_tickets.status'         => [1, 2, 3, 4],
                    'glpi_tickets.is_deleted'     => 0,
                ],
                'GROUPBY'    => ['glpi_items_tickets.itemtype', 'glpi_items_tickets.items_id'],
            ]) as $row) {
                $out[(string) $row['itemtype']][(int) $row['items_id']] = (int) $row['cnt'];
            }
        } catch (\Throwable $e) {
            return null;
        }
        return $out;
    }

    /**
     * Days since each node's inventory agent last reported, in ONE query.
     * Nodes without an agent row simply don't appear (no signal ≠ unhealthy).
     *
     * @param array<string,int[]> $byType itemtype => ids present in the graph
     * @return array<string,array<int,int>> itemtype => id => days since contact
     */
    private static function agentStaleness(array $byType): array
    {
        global $DB;

        if ($byType === [] || !$DB->tableExists('glpi_agents')) {
            return [];
        }

        $types  = array_keys($byType);
        $allIds = [];
        foreach ($byType as $ids) {
            foreach ($ids as $id) { $allIds[$id] = true; }
        }

        $out = [];
        try {
            foreach ($DB->request([
                'SELECT' => ['itemtype', 'items_id', 'MAX' => 'last_contact AS last'],
                'FROM'   => 'glpi_agents',
                'WHERE'  => ['itemtype' => $types, 'items_id' => array_keys($allIds)],
                'GROUPBY'=> ['itemtype', 'items_id'],
            ]) as $row) {
                if (empty($row['last'])) {
                    continue;
                }
                $ts = strtotime((string) $row['last']);
                if ($ts === false) {
                    continue;
                }
                $out[(string) $row['itemtype']][(int) $row['items_id']]
                    = max(0, (int) floor((time() - $ts) / 86400));
            }
        } catch (\Throwable $e) {
            return [];
        }
        return $out;
    }

    /**
     * GLPI stores compound colors as CSS rgba() strings sometimes; normalize
     * to a #rrggbb hex when possible, else pass through unchanged.
     */
    private static function normalizeColor(?string $color): string
    {
        $color = trim((string) $color);
        if ($color === '') {
            return '#6B7280';
        }
        if (preg_match('/^#[0-9a-fA-F]{3,8}$/', $color)) {
            return $color;
        }
        if (preg_match('/rgba?\s*\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/i', $color, $m)) {
            return sprintf('#%02x%02x%02x', (int) $m[1], (int) $m[2], (int) $m[3]);
        }
        return $color; // CSS named color or other — vis-network accepts it
    }
}
