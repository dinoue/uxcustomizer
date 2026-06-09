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
 *   - Hover edges show the merged "N conn." count when collapsed.
 *   - Search box filters/highlights matching nodes.
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

    let network = null;
    let nodesDS = null;
    let edgesDS = null;
    let allNodes = []; // raw payload (with compoundId)
    let allEdges = []; // raw payload
    let compounds = []; // [{id,name,color,count}]
    let clusterIdByCompound = {}; // compoundId -> cluster id
    let typeStyles = {}; // itemtype -> {color, border, icon, label}

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
     * don't show 12 itemtypes when only 3 are in the graph).
     */
    function buildLegend() {
        if (!legendEl) return;
        const present = new Set();
        allNodes.forEach(n => present.add(n.itemtype));
        const items = [];
        present.forEach(it => {
            const style = typeStyles[it] || { color: '#9CA3AF', label: it };
            items.push(
                '<span class="uxc-impact-legend-item">' +
                    '<span class="uxc-impact-swatch" style="background:' +
                    escapeHtml(style.color) +
                    '"></span>' +
                    escapeHtml(style.label || it) +
                '</span>'
            );
        });
        legendEl.innerHTML = items.join('');
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
        // vis-network shows the `title` field as a tooltip; passing an HTMLElement
        // makes it render as HTML (string => plaintext).
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
        // vis-network: when title is a string it renders as text; when it's an
        // HTMLElement, it renders as HTML. We pre-converted server-side strings
        // to "<div>…</div>" markup, so wrap each in a <template> here.
        nodesDS.forEach(n => {
            if (typeof n.title === 'string' && n.title.indexOf('<') !== -1) {
                const wrap = document.createElement('div');
                wrap.className = 'uxc-impact-tt';
                wrap.innerHTML = n.title;
                nodesDS.update({ id: n.id, title: wrap });
            }
        });
    }

    function clusterAllCompounds() {
        clusterIdByCompound = {};
        compounds.forEach(c => clusterCompound(c.id));
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
            // Clean reverse map
            for (const k of Object.keys(clusterIdByCompound)) {
                if (clusterIdByCompound[k] === clusterId) {
                    delete clusterIdByCompound[k];
                    break;
                }
            }
        }
    }

    function expandAll() {
        Object.values(clusterIdByCompound).forEach(id => {
            if (network.isCluster(id)) network.openCluster(id);
        });
        clusterIdByCompound = {};
    }

    function bindEvents() {
        // Double-click anywhere: expand the clicked cluster (if any).
        network.on('doubleClick', params => {
            if (params.nodes.length === 0) return;
            const id = params.nodes[0];
            if (network.isCluster(id)) {
                expandCluster(id);
            }
        });

        // Single click: show side-panel details.
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
        if (searchEl) {
            searchEl.addEventListener('input', () => {
                const q = searchEl.value.trim().toLowerCase();
                if (!q) {
                    network.unselectAll();
                    return;
                }
                const hits = nodesDS.get().filter(n =>
                    (n._name || '').toLowerCase().indexOf(q) !== -1
                );
                if (hits.length) {
                    network.selectNodes(hits.map(n => n.id));
                    network.focus(hits[0].id, { scale: 1.0, animation: { duration: 300 } });
                }
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

    // The script is loaded at the end of the body, so DOMContentLoaded may
    // already have fired. Handle both cases.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
