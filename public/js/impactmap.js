/*
 * UX Customizer — Impact Map client
 *
 * Renders GLPI's native impact graph (read from glpi_impactrelations /
 * glpi_impactitems / glpi_impactcompounds) with vis-network.
 *
 * UX:
 *   - Compounds (GLPI's named groups) start COLLAPSED — each shows as a single
 *     coloured node with "(N)" member count. Double-click expands.
 *   - Single node click → side panel (name, itemtype, "Open in GLPI" link).
 *   - Cluster edges show merged "N conn." labels (Faddom-style).
 *   - Legend pills filter visibility by itemtype.
 *   - Toolbar toggles between force-directed and hierarchical (tree) layout.
 *   - Search dims non-matching nodes (instead of selecting them).
 *
 * License: GPL-3.0-or-later
 */
(function () {
    'use strict';

    const cfg = window.UxcImpactConfig;
    if (!cfg) {
        return;
    }
    const root = document.getElementById('uxc-impact');
    if (!root) {
        return;
    }
    if (typeof window.vis === 'undefined' || typeof window.vis.Network !== 'function') {
        root.innerHTML =
            '<div class="alert alert-danger m-3">' +
            'vis-network library failed to load. Check that public/js/vis-network.min.js is present.' +
            '</div>';
        return;
    }

    const i18n = cfg.i18n || {};
    const t = (k, fallback) => (i18n[k] || fallback || k);

    const canvas = root.querySelector('.uxc-impact-canvas');
    const sidePanel = root.querySelector('.uxc-impact-side');
    const statusEl = root.querySelector('.uxc-impact-status');
    const legendEl = root.querySelector('.uxc-impact-legend');
    const searchEl = root.querySelector('.uxc-impact-search');
    const btnExpandAll = root.querySelector('.uxc-impact-expand-all');
    const btnCollapseAll = root.querySelector('.uxc-impact-collapse-all');
    const btnFit = root.querySelector('.uxc-impact-fit');
    // Layout mode select: flow (dagre LR — native Impact Analysis look),
    // force (physics), tree (vis hierarchical top-down).
    const layoutSel = root.querySelector('.uxc-impact-layoutsel');
    // Depth selects (only present in the on-asset tab; null on the config page).
    const fwdSel = root.querySelector('.uxc-impact-forward');
    const bwdSel = root.querySelector('.uxc-impact-backward');
    // Auto-group-by-type checkbox + PNG export button.
    const groupChk = root.querySelector('.uxc-impact-autogroup');
    const btnExport = root.querySelector('.uxc-impact-export');

    let network = null;
    let nodesDS = null;
    let edgesDS = null;
    let allNodes = []; // raw payload (with compoundId)
    let allEdges = []; // raw payload
    let compounds = []; // [{id,name,color,count}]
    let clusterIdByCompound = {}; // compoundId -> cluster id
    let typeClusterIds = {}; // itemtype -> cluster id (auto-grouping)
    let clusterMeta = {}; // cluster id -> {name, color, count, kind: 'compound'|'type'}
    let typeStyles = {}; // itemtype -> {color, border, icon, label}

    // iTop-style auto-grouping: when more than this many LOOSE nodes of the
    // same itemtype are on the canvas, collapse them into one type cluster.
    const TYPE_GROUP_THRESHOLD = 8;

    // ── Reactive UI state ────────────────────────────────────────────────────
    const state = {
        hiddenTypes: new Set(),  // itemtypes hidden via legend pills
        layoutMode: 'force',     // 'flow' (dagre LR) | 'force' | 'tree'
        search: '',              // current search query (lowercase)
    };

    function setStatus(msg, level) {
        if (!statusEl) return;
        statusEl.textContent = msg || '';
        statusEl.className =
            'uxc-impact-status' + (level ? ' uxc-impact-status--' + level : '');
    }

    function escapeHtml(s) {
        return String(s == null ? '' : s)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    /**
     * Build the legend from styles actually present in the dataset (so we
     * don't show 12 itemtypes when only 3 are in the graph). Each item is
     * a clickable pill: click toggles visibility of all raw nodes of that
     * type (cluster nodes ignore the filter).
     */
    function buildLegend() {
        if (!legendEl) return;
        const present = new Set();
        allNodes.forEach(n => present.add(n.itemtype));
        const items = [];
        present.forEach(it => {
            const style = typeStyles[it] || { color: '#9CA3AF', label: it };
            const isOff = state.hiddenTypes.has(it);
            const cls = 'uxc-impact-legend-item' + (isOff ? ' uxc-impact-legend-item--off' : '');
            const title = isOff
                ? t('show_type', 'Click to show')
                : t('hide_type', 'Click to hide');
            items.push(
                '<button type="button" class="' + cls + '" data-type="' +
                    escapeHtml(it) + '" title="' + escapeHtml(title) + '">' +
                    '<span class="uxc-impact-swatch" style="background:' +
                    escapeHtml(style.color) + '"></span>' +
                    escapeHtml(style.label || it) +
                '</button>'
            );
        });
        legendEl.innerHTML = items.join('');
        // Wire click handlers.
        legendEl.querySelectorAll('.uxc-impact-legend-item').forEach(btn => {
            btn.addEventListener('click', () => {
                const it = btn.getAttribute('data-type');
                if (!it) return;
                if (state.hiddenTypes.has(it)) {
                    state.hiddenTypes.delete(it);
                } else {
                    state.hiddenTypes.add(it);
                }
                applyTypeFilter();
                buildLegend(); // refresh pill states
            });
        });
    }

    // Health overlay colors (SysAid/ServiceNow-style status on the map).
    const HEALTH_BORDER = { crit: '#d63939', warn: '#f59f00' };

    function buildVisNodes() {
        return allNodes.map(n => {
            typeStyles[n.itemtype] = {
                color: n.color,
                border: n.border,
                icon: n.icon,
                label: (n.label_type || n.itemtype),
            };
            // Health overlay: warn/crit nodes get a thick status border. 'ok'
            // and unknown stay with the itemtype border (no noise).
            // Seed nodes (the scoped asset / the ticket's linked assets) are
            // emphasized: thicker dark border + larger label — health colors
            // still win over the seed border so problems stay visible.
            const level = n.health && n.health.level;
            const isSeed = !!n.seed;
            const border = HEALTH_BORDER[level] || (isSeed ? '#111827' : n.border);
            const borderWidth = (HEALTH_BORDER[level] || isSeed) ? 3 : 2;
            return {
                id: n.id,
                label: n.name,
                title: n.title, // HTML tooltip (network is created with html title support)
                shape: 'box',
                color: {
                    background: n.color,
                    border: border,
                    highlight: { background: n.color, border: HEALTH_BORDER[level] || '#111827' },
                    hover: { background: n.color, border: HEALTH_BORDER[level] || '#111827' },
                },
                borderWidth: borderWidth,
                font: { color: pickFontColor(n.color), size: isSeed ? 14 : 13, face: 'inherit' },
                shapeProperties: { borderRadius: 6 },
                margin: 8,
                widthConstraint: { minimum: 60, maximum: 180 },
                opacity: 1,
                // Custom payload for clustering / side panel:
                _itemtype: n.itemtype,
                _items_id: n.items_id,
                _name: n.name,
                _url: n.url,
                _compoundId: n.compoundId,
                _health: n.health || null,
            };
        });
    }

    function buildVisEdges() {
        // NB: no per-edge `smooth` here — edge curvature is a global option
        // set per layout mode (smoothFor), so mode switches restyle all edges.
        return allEdges.map((e, idx) => ({
            id: 'e' + idx,
            from: e.from,
            to: e.to,
            arrows: { to: { enabled: true, scaleFactor: 0.6 } },
            color: { color: '#94a3b8', highlight: '#1f2937', hover: '#1f2937' },
            width: 1.5,
        }));
    }

    /** Edge curvature per layout mode (horizontal flow / vertical tree / organic). */
    function smoothFor(mode) {
        if (mode === 'flow') {
            return { enabled: true, type: 'cubicBezier', forceDirection: 'horizontal', roundness: 0.45 };
        }
        if (mode === 'tree') {
            return { enabled: true, type: 'cubicBezier', forceDirection: 'vertical', roundness: 0.45 };
        }
        return { enabled: true, type: 'dynamic' };
    }

    /**
     * Compute a dagre left-to-right layered layout ("Flow") — the same
     * algorithm GLPI's native Impact Analysis uses (cytoscape-dagre, rankdir
     * LR) — and return {nodeId: {x,y}}. Cycles are handled by dagre's greedy
     * acyclicer. Width is estimated from the label since vis box nodes size
     * to their text.
     */
    function computeDagrePositions(nodes, edges) {
        const g = new dagre.graphlib.Graph();
        g.setGraph({
            rankdir: 'LR',
            nodesep: 30,
            ranksep: 110,
            marginx: 20,
            marginy: 20,
            acyclicer: 'greedy',
            ranker: 'network-simplex',
        });
        g.setDefaultEdgeLabel(() => ({}));
        nodes.forEach(n => {
            const label = String(n._name || n.label || '');
            const w = Math.max(70, Math.min(190, label.length * 7.5 + 30));
            g.setNode(n.id, { width: w, height: 38 });
        });
        edges.forEach(e => {
            if (g.hasNode(e.from) && g.hasNode(e.to)) {
                g.setEdge(e.from, e.to);
            }
        });
        dagre.layout(g);
        const out = {};
        g.nodes().forEach(id => {
            const p = g.node(id);
            if (p) {
                out[id] = { x: p.x, y: p.y };
            }
        });
        return out;
    }

    /**
     * Pick black/white text for a node fill so labels stay readable.
     * Standard YIQ luma threshold (no extra dependencies).
     */
    function pickFontColor(hex) {
        if (!hex || hex[0] !== '#' || (hex.length !== 7 && hex.length !== 4)) {
            return '#111827';
        }
        let h = hex.substring(1);
        if (h.length === 3) {
            h = h[0] + h[0] + h[1] + h[1] + h[2] + h[2];
        }
        const r = parseInt(h.substring(0, 2), 16);
        const g = parseInt(h.substring(2, 4), 16);
        const b = parseInt(h.substring(4, 6), 16);
        const yiq = (r * 299 + g * 587 + b * 114) / 1000;
        return yiq >= 150 ? '#111827' : '#ffffff';
    }

    /**
     * Build the fetch URL by appending live depth-select values (if the
     * selects are present on the page — the on-asset tab has them; the
     * org-wide config page does not, in which case the bare cfg.dataUrl wins).
     */
    function buildFetchUrl() {
        let url = cfg.dataUrl;
        const parts = [];
        if (fwdSel) parts.push('forward=' + encodeURIComponent(fwdSel.value));
        if (bwdSel) parts.push('backward=' + encodeURIComponent(bwdSel.value));
        if (parts.length) {
            url += (url.indexOf('?') >= 0 ? '&' : '?') + parts.join('&');
        }
        return url;
    }

    // ── Position persistence (ServiceNow-style stable layout) ───────────────
    // Saved per scope (full fetch URL = asset + depths) in localStorage. When
    // a later load finds positions for EVERY node in the graph, they're applied
    // as preset coordinates and physics is skipped entirely — instant, stable
    // render. Any topology change (new/removed node) falls back to a seeded
    // physics run, which is itself deterministic (layout.randomSeed below).

    const POS_STORE = 'uxcImpactPositions';
    const POS_MAX_SCOPES = 20; // prune oldest beyond this many saved scopes

    function scopeKey() {
        return buildFetchUrl();
    }

    function loadSavedPositions() {
        try {
            const all = JSON.parse(localStorage.getItem(POS_STORE) || '{}');
            const entry = all[scopeKey()];
            return (entry && entry.positions) ? entry.positions : null;
        } catch (e) {
            return null;
        }
    }

    function savePositions() {
        if (!network || !nodesDS) return;
        let pos = {};
        try {
            pos = network.getPositions(nodesDS.getIds());
        } catch (e) {
            return; // some ids mid-cluster transition — skip this save
        }
        try {
            const all = JSON.parse(localStorage.getItem(POS_STORE) || '{}');
            all[scopeKey()] = { savedAt: Date.now(), positions: pos };
            const keys = Object.keys(all);
            if (keys.length > POS_MAX_SCOPES) {
                keys.sort((a, b) => (all[a].savedAt || 0) - (all[b].savedAt || 0));
                keys.slice(0, keys.length - POS_MAX_SCOPES).forEach(k => delete all[k]);
            }
            localStorage.setItem(POS_STORE, JSON.stringify(all));
        } catch (e) {
            // localStorage full or disabled — persistence is best-effort
        }
    }

    function init() {
        fetchAndRender();
        // Depth selects trigger a full reload (destroy network → re-fetch).
        if (fwdSel) fwdSel.addEventListener('change', reload);
        if (bwdSel) bwdSel.addEventListener('change', reload);
    }

    function fetchAndRender() {
        setStatus(t('loading', 'Loading…'), 'info');
        fetch(buildFetchUrl(), { credentials: 'same-origin', headers: { Accept: 'application/json' } })
            .then(r => r.json())
            .then(payload => {
                if (!payload || !payload.ok || !payload.data) {
                    throw new Error((payload && payload.error) || 'bad response');
                }
                renderGraph(payload.data);
            })
            .catch(err => {
                setStatus(t('failed', 'Failed to load:') + ' ' + err.message, 'error');
            });
    }

    /**
     * Tear down the current network instance and re-fetch from scratch. Used
     * when depth selects change (or any future control that affects the
     * server-side query).
     */
    function reload() {
        if (network) {
            try { network.destroy(); } catch (e) { /* ignore */ }
            network = null;
        }
        nodesDS = null;
        edgesDS = null;
        clusterIdByCompound = {};
        typeClusterIds = {};
        clusterMeta = {};
        state.hiddenTypes = new Set();
        state.search = '';
        if (searchEl) searchEl.value = '';
        if (sidePanel) sidePanel.classList.remove('uxc-impact-side--open');
        const emptyEl = root.querySelector('.uxc-impact-empty');
        if (emptyEl) emptyEl.style.display = 'none';
        fetchAndRender();
    }

    function renderGraph(data) {
        allNodes = data.nodes || [];
        allEdges = data.edges || [];
        compounds = data.compounds || [];
        const meta = data.meta || {};

        if (allNodes.length === 0) {
            root.querySelector('.uxc-impact-empty').style.display = '';
            setStatus('', '');
            return;
        }

        const visNodes = buildVisNodes();
        const mode = layoutSel ? layoutSel.value : 'force';
        state.layoutMode = mode;

        // Layout decision, in priority order:
        //   1. Saved positions covering every node (user's arrangement) — but
        //      not in tree mode, where vis's hierarchical engine owns x/y.
        //   2. Flow mode → dagre LR computed positions (native-Impact look).
        //   3. Tree mode → vis hierarchical (deterministic, no physics).
        //   4. Force → seeded physics run (deterministic per seed).
        const saved = loadSavedPositions();
        const preset = mode !== 'tree' && !!saved && visNodes.every(
            n => saved[n.id] && typeof saved[n.id].x === 'number' && typeof saved[n.id].y === 'number'
        );
        let staticLayout = preset;
        if (preset) {
            visNodes.forEach(n => {
                n.x = saved[n.id].x;
                n.y = saved[n.id].y;
            });
        } else if (mode === 'flow' && typeof window.dagre !== 'undefined') {
            const pos = computeDagrePositions(visNodes, allEdges);
            visNodes.forEach(n => {
                if (pos[n.id]) {
                    n.x = pos[n.id].x;
                    n.y = pos[n.id].y;
                }
            });
            staticLayout = true;
        } else if (mode === 'tree') {
            staticLayout = true; // hierarchical engine positions synchronously
        }

        nodesDS = new vis.DataSet(visNodes);
        edgesDS = new vis.DataSet(buildVisEdges());

        const options = {
            interaction: {
                hover: true,
                tooltipDelay: 150,
                multiselect: false,
                navigationButtons: false,
                keyboard: true,
            },
            nodes: {
                borderWidth: 2,
                shadow: { enabled: true, size: 4, x: 0, y: 1, color: 'rgba(0,0,0,0.08)' },
            },
            edges: {
                hoverWidth: 0.5,
                selectionWidth: 1,
                smooth: smoothFor(mode),
            },
            physics: staticLayout
                ? { enabled: false }
                : {
                    enabled: true,
                    solver: 'forceAtlas2Based',
                    forceAtlas2Based: {
                        gravitationalConstant: -45,
                        centralGravity: 0.012,
                        springLength: 130,
                        springConstant: 0.08,
                        damping: 0.6,
                        avoidOverlap: 0.6,
                    },
                    stabilization: { iterations: 120, fit: true },
                },
            layout: {
                // Fixed seed → the same graph lays out identically on every
                // load (no more drift between visits). Static layouts (preset,
                // dagre, hierarchical) ignore the seed entirely. improvedLayout
                // (Kamada-Kawai pre-pass) is costly — only under ~150 nodes.
                randomSeed: 116,
                improvedLayout: !staticLayout && visNodes.length <= 150,
                hierarchical: mode === 'tree' ? hierarchicalOpts() : false,
            },
        };

        network = new vis.Network(canvas, { nodes: nodesDS, edges: edgesDS }, options);

        // Re-enable HTML tooltips by patching the rendered title to be a DOM node.
        upgradeHtmlTooltips();

        if (staticLayout) {
            // No physics pass — finalize immediately.
            finalizeRender(meta, false);
        } else {
            network.once('stabilizationIterationsDone', () => finalizeRender(meta, true));
        }

        bindEvents();
    }

    /** vis hierarchical options for the Tree (top-down) mode. */
    function hierarchicalOpts() {
        return {
            direction: 'UD',
            sortMethod: 'directed',
            levelSeparation: 140,
            nodeSpacing: 140,
            treeSpacing: 200,
            blockShifting: true,
            edgeMinimization: true,
            parentCentralization: true,
        };
    }

    /**
     * Post-layout finishing common to both render paths. `fromPhysics` is true
     * when a stabilization run just ended (positions are fresh → persist them
     * BEFORE clustering hides member nodes); false on a preset render.
     */
    function finalizeRender(meta, fromPhysics) {
        if (fromPhysics) {
            network.setOptions({ physics: { enabled: false } });
            savePositions();
        } else if (state.layoutMode !== 'tree') {
            // Static render (preset or dagre flow): persist so the next load
            // short-circuits to these exact coordinates. Tree positions are
            // owned by vis's hierarchical engine — never save those, or they
            // would leak into flow/force renders as a stale "arrangement".
            savePositions();
        }
        reclusterAll();
        buildLegend();
        if (!fromPhysics) {
            network.fit();
        }
        const noticeParts = [];
        noticeParts.push(
            (meta.total_nodes || allNodes.length) + ' ' + t('nodes', 'nodes')
        );
        noticeParts.push(
            (meta.total_edges || allEdges.length) + ' ' + t('relations', 'relations')
        );
        if (meta.truncated) {
            noticeParts.push(
                '<span class="text-warning">' +
                t('truncated', 'truncated to ') + (meta.max_nodes || allNodes.length) +
                '</span>'
            );
        }
        statusEl.innerHTML = noticeParts.join(' · ');
    }

    function upgradeHtmlTooltips() {
        nodesDS.forEach(n => {
            if (typeof n.title === 'string' && n.title.indexOf('<') !== -1) {
                const wrap = document.createElement('div');
                wrap.className = 'uxc-impact-tt';
                wrap.innerHTML = n.title;
                nodesDS.update({ id: n.id, title: wrap });
            }
        });
    }

    // ── Clustering ──────────────────────────────────────────────────────────

    function reclusterAll() {
        clusterIdByCompound = {};
        compounds.forEach(c => clusterCompound(c.id));
        if (groupChk && groupChk.checked) {
            clusterLooseTypes();
        }
        // After all clusters are formed, label merged cluster edges with their
        // base-edge count ("3 conn." Faddom-style).
        labelAllClusterEdges();
    }

    /**
     * iTop-style auto-grouping: collapse loose nodes (not inside any cluster)
     * into one cluster per itemtype when the type exceeds the threshold.
     * Dashed border distinguishes auto type groups from named compounds.
     */
    function clusterLooseTypes() {
        if (!network) return;
        typeClusterIds = {};
        // Count loose nodes per type (findNode path length 1 = top level).
        const looseByType = {};
        nodesDS.getIds().forEach(id => {
            let path = [];
            try { path = network.findNode(id); } catch (e) { return; }
            if (path.length === 1) {
                const n = nodesDS.get(id);
                if (n && !n.hidden) {
                    (looseByType[n._itemtype] = looseByType[n._itemtype] || []).push(id);
                }
            }
        });
        Object.entries(looseByType).forEach(([itemtype, ids]) => {
            if (ids.length <= TYPE_GROUP_THRESHOLD) return;
            const style = typeStyles[itemtype] || { color: '#9CA3AF', label: itemtype };
            const clusterId = 'type:' + itemtype;
            const idSet = new Set(ids);
            network.cluster({
                joinCondition: nodeOpts => idSet.has(nodeOpts.id),
                processProperties: (clusterOptions, childNodes) => {
                    clusterOptions.label = (style.label || itemtype) + ' (' + childNodes.length + ')';
                    clusterMeta[clusterId] = {
                        name: style.label || itemtype,
                        color: style.color,
                        count: childNodes.length,
                        kind: 'type',
                    };
                    return clusterOptions;
                },
                clusterNodeProperties: {
                    id: clusterId,
                    shape: 'box',
                    margin: 12,
                    borderWidth: 2,
                    color: {
                        background: style.color,
                        border: '#111827',
                        highlight: { background: style.color, border: '#111827' },
                    },
                    font: { color: pickFontColor(style.color), size: 14, face: 'inherit' },
                    shapeProperties: { borderRadius: 8, borderDashes: [6, 4] },
                },
            });
            typeClusterIds[itemtype] = clusterId;
        });
    }

    function clusterCompound(compoundId) {
        if (!network) return;
        const meta = compounds.find(c => c.id === compoundId);
        if (!meta) return;
        const clusterId = 'cluster:' + compoundId;
        const opts = {
            joinCondition: nodeOpts => nodeOpts._compoundId === compoundId,
            processProperties: (clusterOptions, childNodes) => {
                clusterOptions.label = meta.name + ' (' + childNodes.length + ')';
                clusterMeta[clusterId] = {
                    name: meta.name,
                    color: meta.color || '#6B7280',
                    count: childNodes.length,
                    kind: 'compound',
                };
                return clusterOptions;
            },
            clusterNodeProperties: {
                id: clusterId,
                shape: 'box',
                margin: 12,
                borderWidth: 2,
                color: {
                    background: meta.color || '#6B7280',
                    border: '#111827',
                    highlight: { background: meta.color || '#6B7280', border: '#111827' },
                },
                font: { color: pickFontColor(meta.color || '#6B7280'), size: 14, face: 'inherit' },
                shapeProperties: { borderRadius: 8 },
                _clusterCompound: compoundId,
            },
        };
        network.cluster(opts);
        clusterIdByCompound[compoundId] = clusterId;
    }

    function expandCluster(clusterId) {
        if (network && network.isCluster(clusterId)) {
            network.openCluster(clusterId);
            for (const k of Object.keys(clusterIdByCompound)) {
                if (clusterIdByCompound[k] === clusterId) {
                    delete clusterIdByCompound[k];
                    break;
                }
            }
            for (const k of Object.keys(typeClusterIds)) {
                if (typeClusterIds[k] === clusterId) {
                    delete typeClusterIds[k];
                    break;
                }
            }
            delete clusterMeta[clusterId];
            // Re-apply filter so newly-revealed raw nodes respect the legend.
            applyTypeFilter();
            // Members just got real on-canvas positions — persist them.
            savePositions();
        }
    }

    function allClusterIds() {
        return Object.values(clusterIdByCompound).concat(Object.values(typeClusterIds));
    }

    function expandAll() {
        allClusterIds().forEach(id => {
            if (network.isCluster(id)) network.openCluster(id);
        });
        clusterIdByCompound = {};
        typeClusterIds = {};
        clusterMeta = {};
        applyTypeFilter();
        savePositions();
    }

    /**
     * Walk every cluster edge in the network and label it with the number of
     * base (real) edges it merges. Uses the public vis-network APIs
     * getConnectedEdges() and getBaseEdges(); clustering.updateEdge() is also
     * public though under-documented — wrapped in try/catch for safety.
     */
    function labelAllClusterEdges() {
        if (!network) return;
        allClusterIds().forEach(clusterId => {
            if (!network.isCluster(clusterId)) return;
            const eids = network.getConnectedEdges(clusterId);
            eids.forEach(eid => {
                try {
                    const base = network.getBaseEdges(eid);
                    if (base && base.length > 1) {
                        network.clustering.updateEdge(eid, {
                            label: base.length + ' ' + t('conn', 'conn.'),
                            font: {
                                size: 10,
                                color: '#1f2937',
                                background: 'rgba(255,255,255,0.92)',
                                strokeWidth: 0,
                                align: 'middle',
                            },
                        });
                    }
                } catch (e) {
                    // edge may have just been removed/expanded — ignore
                }
            });
        });
    }

    // ── Filtering by itemtype (legend pills) ────────────────────────────────

    function applyTypeFilter() {
        if (!nodesDS || !edgesDS) return;
        const hidden = state.hiddenTypes;

        // 1. Update raw node visibility.
        const nodeUpdates = [];
        nodesDS.forEach(n => {
            const should = hidden.has(n._itemtype);
            if ((n.hidden === true) !== should) {
                nodeUpdates.push({ id: n.id, hidden: should });
            }
        });
        if (nodeUpdates.length) nodesDS.update(nodeUpdates);

        // 2. Hide edges that have a hidden endpoint.
        const hiddenIds = new Set();
        nodesDS.forEach(n => { if (n.hidden) hiddenIds.add(n.id); });
        const edgeUpdates = [];
        edgesDS.forEach(e => {
            const should = hiddenIds.has(e.from) || hiddenIds.has(e.to);
            if ((e.hidden === true) !== should) {
                edgeUpdates.push({ id: e.id, hidden: should });
            }
        });
        if (edgeUpdates.length) edgesDS.update(edgeUpdates);
    }

    // ── Layout modes (Flow / Force / Tree) ──────────────────────────────────

    /**
     * Switch the live network to another layout without re-fetching.
     *   flow  — dagre LR positions applied to the dataset, physics stays off
     *   force — one bounded physics run, then freeze + persist
     *   tree  — vis hierarchical engine takes over x/y
     */
    function applyLayoutMode(mode) {
        state.layoutMode = mode;
        if (!network) return;

        const anim = { animation: { duration: 400 } };

        if (mode === 'tree') {
            network.setOptions({
                layout: { hierarchical: hierarchicalOpts() },
                physics: { enabled: false },
                edges: { smooth: smoothFor(mode) },
            });
            try { network.fit(anim); } catch (e) { /* ignore */ }
            return;
        }

        // Leaving tree (or staying flat): release the hierarchical engine.
        network.setOptions({
            layout: { hierarchical: false },
            physics: { enabled: false },
            edges: { smooth: smoothFor(mode) },
        });

        if (mode === 'flow' && typeof window.dagre !== 'undefined') {
            const current = nodesDS.get();
            const pos = computeDagrePositions(current, allEdges);
            const updates = [];
            current.forEach(n => {
                if (pos[n.id]) {
                    updates.push({ id: n.id, x: pos[n.id].x, y: pos[n.id].y });
                }
            });
            nodesDS.update(updates);
            repositionClusters(pos);
            savePositions();
            try { network.fit(anim); } catch (e) { /* ignore */ }
        } else if (mode === 'force') {
            network.setOptions({
                physics: {
                    enabled: true,
                    solver: 'forceAtlas2Based',
                    forceAtlas2Based: {
                        gravitationalConstant: -45,
                        centralGravity: 0.012,
                        springLength: 130,
                        springConstant: 0.08,
                        damping: 0.6,
                        avoidOverlap: 0.6,
                    },
                    stabilization: false,
                },
            });
            network.once('stabilizationIterationsDone', () => {
                network.setOptions({ physics: { enabled: false } });
                savePositions();
                try { network.fit(anim); } catch (e) { /* ignore */ }
            });
            network.stabilize(120);
        }
    }

    /**
     * After a flow re-layout, collapsed cluster nodes are not in nodesDS so
     * dagre never moved them — drop each at the average of its members'
     * computed positions so groups land where their content belongs.
     */
    function repositionClusters(pos) {
        const moveToMemberAvg = (clusterId, isMember) => {
            if (!network.isCluster(clusterId)) return;
            let sx = 0, sy = 0, n = 0;
            nodesDS.get().forEach(node => {
                if (isMember(node) && pos[node.id]) {
                    sx += pos[node.id].x;
                    sy += pos[node.id].y;
                    n++;
                }
            });
            if (n > 0) {
                try { network.moveNode(clusterId, sx / n, sy / n); } catch (e) { /* ignore */ }
            }
        };
        Object.entries(clusterIdByCompound).forEach(([compoundId, clusterId]) => {
            moveToMemberAvg(clusterId, node => node._compoundId === Number(compoundId));
        });
        Object.entries(typeClusterIds).forEach(([itemtype, clusterId]) => {
            moveToMemberAvg(clusterId, node => node._itemtype === itemtype);
        });
    }

    // ── Search (dim non-matching nodes) ─────────────────────────────────────

    function applySearch(q) {
        if (!nodesDS) return;
        state.search = q || '';
        const ql = state.search.toLowerCase();

        if (!ql) {
            // Reset opacity to 1 for any node that isn't already.
            const updates = [];
            nodesDS.forEach(n => {
                if (n.opacity !== 1) updates.push({ id: n.id, opacity: 1 });
            });
            if (updates.length) nodesDS.update(updates);
            return;
        }

        let firstMatch = null;
        const updates = [];
        nodesDS.forEach(n => {
            const matches = (n._name || '').toLowerCase().indexOf(ql) !== -1;
            const op = matches ? 1 : 0.15;
            if (n.opacity !== op) updates.push({ id: n.id, opacity: op });
            if (matches && !firstMatch) firstMatch = n.id;
        });
        if (updates.length) nodesDS.update(updates);
        if (firstMatch) {
            try {
                network.focus(firstMatch, { scale: 1.0, animation: { duration: 250 } });
            } catch (e) {
                // node may be inside a collapsed cluster — ignore
            }
        }
    }

    // ── PNG export ──────────────────────────────────────────────────────────

    /**
     * Snapshot the network canvas onto a solid background (the raw canvas is
     * transparent — pasted into docs it would look broken) and download it.
     */
    function exportPng() {
        if (!network) return;
        let src;
        try {
            src = network.canvas.frame.canvas;
        } catch (e) {
            return;
        }
        const out = document.createElement('canvas');
        out.width = src.width;
        out.height = src.height;
        const ctx = out.getContext('2d');
        const bg = getComputedStyle(canvas.closest('.uxc-impact-stage') || canvas).backgroundColor;
        ctx.fillStyle = (bg && bg !== 'rgba(0, 0, 0, 0)') ? bg : '#ffffff';
        ctx.fillRect(0, 0, out.width, out.height);
        ctx.drawImage(src, 0, 0);

        const a = document.createElement('a');
        const stamp = new Date().toISOString().slice(0, 16).replace(/[:T]/g, '-');
        a.download = 'impact-map-' + stamp + '.png';
        a.href = out.toDataURL('image/png');
        document.body.appendChild(a);
        a.click();
        document.body.removeChild(a);
    }

    // ── Event wiring ────────────────────────────────────────────────────────

    function bindEvents() {
        network.on('doubleClick', params => {
            if (params.nodes.length === 0) return;
            const id = params.nodes[0];
            if (network.isCluster(id)) {
                expandCluster(id);
            }
        });

        network.on('selectNode', params => {
            const id = params.nodes[0];
            if (network.isCluster(id)) {
                showClusterPanel(clusterMeta[id], id);
            } else {
                const node = nodesDS.get(id);
                showNodePanel(node);
            }
        });
        network.on('deselectNode', () => sidePanel.classList.remove('uxc-impact-side--open'));

        // Manual node moves are the user telling us where things belong —
        // persist so the arrangement survives reloads (ServiceNow behaviour).
        network.on('dragEnd', params => {
            if (params.nodes && params.nodes.length) {
                savePositions();
            }
        });

        if (btnExpandAll) {
            btnExpandAll.addEventListener('click', () => expandAll());
        }
        if (btnCollapseAll) {
            btnCollapseAll.addEventListener('click', () => reclusterAll());
        }
        if (groupChk) {
            groupChk.addEventListener('change', () => {
                if (groupChk.checked) {
                    clusterLooseTypes();
                    labelAllClusterEdges();
                } else {
                    // Open only the auto type groups; named compounds stay.
                    Object.values(typeClusterIds).forEach(id => {
                        if (network.isCluster(id)) network.openCluster(id);
                        delete clusterMeta[id];
                    });
                    typeClusterIds = {};
                    applyTypeFilter();
                }
            });
        }
        if (btnExport) {
            btnExport.addEventListener('click', exportPng);
        }
        if (btnFit) {
            btnFit.addEventListener('click', () => network.fit({ animation: { duration: 400 } }));
        }
        if (layoutSel) {
            layoutSel.addEventListener('change', () => applyLayoutMode(layoutSel.value));
        }
        if (searchEl) {
            searchEl.addEventListener('input', () => {
                applySearch(searchEl.value.trim());
            });
        }
    }

    function showNodePanel(node) {
        if (!node || !sidePanel) return;
        const style = typeStyles[node._itemtype] || { color: '#9CA3AF', label: node._itemtype, icon: 'ti ti-package' };

        // Health rows (only signals that exist — no "unknown" filler).
        let healthRows = '';
        const h = node._health;
        if (h && h.level) {
            const dot = h.level === 'crit' ? '#d63939' : (h.level === 'warn' ? '#f59f00' : '#2fb344');
            healthRows += '<dt>' + t('health', 'Health') + '</dt><dd>' +
                '<span style="display:inline-block;width:10px;height:10px;border-radius:50%;background:' + dot + ';margin-right:0.35rem"></span>' +
                escapeHtml(t('health_' + h.level, h.level)) + '</dd>';
            if (typeof h.tickets === 'number') {
                healthRows += '<dt>' + t('open_tickets', 'Open tickets') + '</dt><dd>' + escapeHtml(h.tickets) + '</dd>';
            }
            if (typeof h.agent_days === 'number') {
                healthRows += '<dt>' + t('agent_seen', 'Agent seen') + '</dt><dd>' +
                    (h.agent_days === 0
                        ? escapeHtml(t('today', 'today'))
                        : escapeHtml(h.agent_days) + ' ' + escapeHtml(t('days_ago', 'days ago'))) + '</dd>';
            }
        }

        sidePanel.innerHTML =
            '<div class="uxc-impact-side-head" style="background:' + escapeHtml(style.color) + ';color:' + pickFontColor(style.color) + '">' +
                '<i class="' + escapeHtml(style.icon || 'ti ti-package') + ' me-2"></i>' +
                '<strong>' + escapeHtml(node._name) + '</strong>' +
            '</div>' +
            '<div class="uxc-impact-side-body">' +
                '<dl class="uxc-impact-kv">' +
                    '<dt>' + t('type', 'Type') + '</dt><dd>' + escapeHtml(style.label || node._itemtype) + '</dd>' +
                    '<dt>' + t('id', 'ID') + '</dt><dd>#' + escapeHtml(node._items_id) + '</dd>' +
                    healthRows +
                '</dl>' +
                '<a class="btn btn-primary btn-sm" href="' + escapeHtml(node._url) + '">' +
                    '<i class="ti ti-external-link me-1"></i>' + t('open_in_glpi', 'Open in GLPI') +
                '</a>' +
            '</div>';
        sidePanel.classList.add('uxc-impact-side--open');
    }

    function showClusterPanel(meta, clusterId) {
        if (!meta || !sidePanel) return;
        const icon = meta.kind === 'type' ? 'ti ti-category' : 'ti ti-stack-2';
        const kindLabel = meta.kind === 'type' ? t('type_group', 'Type group') : t('group', 'Group');
        sidePanel.innerHTML =
            '<div class="uxc-impact-side-head" style="background:' + escapeHtml(meta.color || '#6B7280') + ';color:' + pickFontColor(meta.color || '#6B7280') + '">' +
                '<i class="' + icon + ' me-2"></i>' +
                '<strong>' + escapeHtml(meta.name) + '</strong>' +
            '</div>' +
            '<div class="uxc-impact-side-body">' +
                '<dl class="uxc-impact-kv">' +
                    '<dt>' + kindLabel + '</dt><dd>' + escapeHtml(meta.name) + '</dd>' +
                    '<dt>' + t('members', 'Members') + '</dt><dd>' + escapeHtml(meta.count) + '</dd>' +
                '</dl>' +
                '<button class="btn btn-outline-primary btn-sm uxc-impact-expand-this">' +
                    '<i class="ti ti-arrows-maximize me-1"></i>' + t('expand', 'Expand') +
                '</button>' +
            '</div>';
        const btn = sidePanel.querySelector('.uxc-impact-expand-this');
        if (btn) {
            btn.addEventListener('click', () => {
                expandCluster(clusterId);
                sidePanel.classList.remove('uxc-impact-side--open');
            });
        }
        sidePanel.classList.add('uxc-impact-side--open');
    }

    // Bottom-of-body script may load after DOMContentLoaded; handle both.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
