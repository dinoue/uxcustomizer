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
    const btnLayout = root.querySelector('.uxc-impact-layout');

    let network = null;
    let nodesDS = null;
    let edgesDS = null;
    let allNodes = []; // raw payload (with compoundId)
    let allEdges = []; // raw payload
    let compounds = []; // [{id,name,color,count}]
    let clusterIdByCompound = {}; // compoundId -> cluster id
    let typeStyles = {}; // itemtype -> {color, border, icon, label}

    // ── Reactive UI state ────────────────────────────────────────────────────
    const state = {
        hiddenTypes: new Set(),  // itemtypes hidden via legend pills
        hierarchical: false,     // tree vs force-directed
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

    function buildVisNodes() {
        return allNodes.map(n => {
            typeStyles[n.itemtype] = {
                color: n.color,
                border: n.border,
                icon: n.icon,
                label: (n.label_type || n.itemtype),
            };
            return {
                id: n.id,
                label: n.name,
                title: n.title, // HTML tooltip (network is created with html title support)
                shape: 'box',
                color: {
                    background: n.color,
                    border: n.border,
                    highlight: { background: n.color, border: '#111827' },
                    hover: { background: n.color, border: '#111827' },
                },
                font: { color: pickFontColor(n.color), size: 13, face: 'inherit' },
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
            };
        });
    }

    function buildVisEdges() {
        return allEdges.map((e, idx) => ({
            id: 'e' + idx,
            from: e.from,
            to: e.to,
            arrows: { to: { enabled: true, scaleFactor: 0.6 } },
            color: { color: '#94a3b8', highlight: '#1f2937', hover: '#1f2937' },
            smooth: { enabled: true, type: 'dynamic' },
            width: 1.5,
        }));
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

    function init() {
        setStatus(t('loading', 'Loading…'), 'info');
        fetch(cfg.dataUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
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

        nodesDS = new vis.DataSet(buildVisNodes());
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
            },
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
                stabilization: { iterations: 220, fit: true },
            },
            layout: { improvedLayout: true },
        };

        network = new vis.Network(canvas, { nodes: nodesDS, edges: edgesDS }, options);

        // Re-enable HTML tooltips by patching the rendered title to be a DOM node.
        upgradeHtmlTooltips();

        network.once('stabilizationIterationsDone', () => {
            network.setOptions({ physics: { enabled: false } });
            clusterAllCompounds();
            buildLegend();
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
        });

        bindEvents();
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

    function clusterAllCompounds() {
        clusterIdByCompound = {};
        compounds.forEach(c => clusterCompound(c.id));
        // After all clusters are formed, label merged cluster edges with their
        // base-edge count ("3 conn." Faddom-style).
        labelAllClusterEdges();
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
            // Re-apply filter so newly-revealed raw nodes respect the legend.
            applyTypeFilter();
        }
    }

    function expandAll() {
        Object.values(clusterIdByCompound).forEach(id => {
            if (network.isCluster(id)) network.openCluster(id);
        });
        clusterIdByCompound = {};
        applyTypeFilter();
    }

    /**
     * Walk every cluster edge in the network and label it with the number of
     * base (real) edges it merges. Uses the public vis-network APIs
     * getConnectedEdges() and getBaseEdges(); clustering.updateEdge() is also
     * public though under-documented — wrapped in try/catch for safety.
     */
    function labelAllClusterEdges() {
        if (!network) return;
        Object.values(clusterIdByCompound).forEach(clusterId => {
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

    // ── Layout toggle (Tree / Force) ────────────────────────────────────────

    function setHierarchical(enable) {
        if (!network) return;
        state.hierarchical = !!enable;
        network.setOptions({
            layout: {
                hierarchical: enable
                    ? {
                          direction: 'UD',          // Up → Down
                          sortMethod: 'directed',
                          levelSeparation: 140,
                          nodeSpacing: 140,
                          treeSpacing: 200,
                          blockShifting: true,
                          edgeMinimization: true,
                          parentCentralization: true,
                      }
                    : false,
            },
            physics: { enabled: !enable },
        });
        // Tree layout repositions everything synchronously; fit to view.
        try {
            network.fit({ animation: { duration: 400 } });
        } catch (e) {
            // ignore
        }
        // Refresh button label.
        if (btnLayout) {
            btnLayout.classList.toggle('active', enable);
            btnLayout.innerHTML = enable
                ? '<i class="ti ti-circles-relation me-1"></i>' + t('layout_force', 'Force layout')
                : '<i class="ti ti-binary-tree me-1"></i>' + t('layout_tree', 'Tree layout');
        }
        // Cluster edges keep their labels in tree layout; no action needed.
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
                const meta = compounds.find(c => clusterIdByCompound[c.id] === id);
                showClusterPanel(meta);
            } else {
                const node = nodesDS.get(id);
                showNodePanel(node);
            }
        });
        network.on('deselectNode', () => sidePanel.classList.remove('uxc-impact-side--open'));

        if (btnExpandAll) {
            btnExpandAll.addEventListener('click', () => expandAll());
        }
        if (btnCollapseAll) {
            btnCollapseAll.addEventListener('click', () => clusterAllCompounds());
        }
        if (btnFit) {
            btnFit.addEventListener('click', () => network.fit({ animation: { duration: 400 } }));
        }
        if (btnLayout) {
            btnLayout.addEventListener('click', () => setHierarchical(!state.hierarchical));
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
        sidePanel.innerHTML =
            '<div class="uxc-impact-side-head" style="background:' + escapeHtml(style.color) + ';color:' + pickFontColor(style.color) + '">' +
                '<i class="' + escapeHtml(style.icon || 'ti ti-package') + ' me-2"></i>' +
                '<strong>' + escapeHtml(node._name) + '</strong>' +
            '</div>' +
            '<div class="uxc-impact-side-body">' +
                '<dl class="uxc-impact-kv">' +
                    '<dt>' + t('type', 'Type') + '</dt><dd>' + escapeHtml(style.label || node._itemtype) + '</dd>' +
                    '<dt>' + t('id', 'ID') + '</dt><dd>#' + escapeHtml(node._items_id) + '</dd>' +
                '</dl>' +
                '<a class="btn btn-primary btn-sm" href="' + escapeHtml(node._url) + '">' +
                    '<i class="ti ti-external-link me-1"></i>' + t('open_in_glpi', 'Open in GLPI') +
                '</a>' +
            '</div>';
        sidePanel.classList.add('uxc-impact-side--open');
    }

    function showClusterPanel(meta) {
        if (!meta || !sidePanel) return;
        sidePanel.innerHTML =
            '<div class="uxc-impact-side-head" style="background:' + escapeHtml(meta.color || '#6B7280') + ';color:' + pickFontColor(meta.color || '#6B7280') + '">' +
                '<i class="ti ti-stack-2 me-2"></i>' +
                '<strong>' + escapeHtml(meta.name) + '</strong>' +
            '</div>' +
            '<div class="uxc-impact-side-body">' +
                '<dl class="uxc-impact-kv">' +
                    '<dt>' + t('group', 'Group') + '</dt><dd>' + escapeHtml(meta.name) + '</dd>' +
                    '<dt>' + t('members', 'Members') + '</dt><dd>' + escapeHtml(meta.count) + '</dd>' +
                '</dl>' +
                '<button class="btn btn-outline-primary btn-sm uxc-impact-expand-this">' +
                    '<i class="ti ti-arrows-maximize me-1"></i>' + t('expand', 'Expand') +
                '</button>' +
            '</div>';
        const btn = sidePanel.querySelector('.uxc-impact-expand-this');
        if (btn) {
            btn.addEventListener('click', () => {
                expandCluster(clusterIdByCompound[meta.id]);
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
