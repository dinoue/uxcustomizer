/**
 * UX Customizer — Tab Order (apply).
 *
 * Loaded on EVERY page via add_javascript (GLPI 11 has no hook to reorder
 * item-form tabs server-side). On an asset form page it detects the itemtype
 * from the URL, fetches the saved tab order, and reorders the rendered
 * Bootstrap .nav-tabs <li> nodes. Unknown/new tabs stay at the bottom.
 *
 * Client-side + DOM-dependent by necessity — kept defensive: each tab's key is
 * extracted from several possible attributes. If it ever stops matching, run
 * `uxcTabDebug()` in the console to dump what was found.
 */
(function () {
    'use strict';

    // Supported asset form pages: basename(*.form.php) → itemtype slug.
    const FORM_RE = /\/([a-z0-9]+)\.form\.php(?:$|[?#])/i;

    // List of supported itemtype slugs (must match TabOrder::ITEMTYPES in src/TabOrder.php).
    const SUPPORTED_ITEMTYPES = new Set([
        'computer', 'monitor', 'networkequipment', 'peripheral', 'phone',
        'printer', 'software', 'rack', 'enclosure', 'pdu', 'cluster'
    ]);

    function rootDoc() {
        if (window.CFG_GLPI && window.CFG_GLPI.root_doc) return window.CFG_GLPI.root_doc;
        // Fallback: derive from this script's own src.
        const s = document.currentScript || [...document.scripts].find(x => /uxcustomizer\/public\/js\/tabreorder\.js/.test(x.src));
        if (s) { const m = s.src.match(/^(.*)\/plugins\/uxcustomizer\//); if (m) return m[1].replace(location.origin, ''); }
        return '';
    }

    function currentSlug() {
        const m = location.pathname.match(FORM_RE);
        const slug = m ? m[1].toLowerCase() : null;
        // Only return slug if it's a supported itemtype; suppress requests for non-asset pages like config.
        return slug && SUPPORTED_ITEMTYPES.has(slug) ? slug : null;
    }

    function tabBar() {
        // GLPI 11's item tab rail is ul#tabspanel (class "nav nav-tabs
        // flex-row flex-md-column"). There are several other .nav-tabs on the
        // page (top navbar, debug toolbar), so target #tabspanel specifically.
        return document.getElementById('tabspanel')
            || document.querySelector('.nav-tabs.flex-md-column')
            || document.querySelector('ul[role="tablist"]');
    }

    // Extract a stable tab key (e.g. "Computer$main") from a tab anchor, trying
    // the several places GLPI may expose it.
    function keyOf(a) {
        if (!a) return null;
        const sources = [
            a.getAttribute('data-glpi-ajax-content'),
            a.getAttribute('href'),
            a.dataset ? a.dataset.bsTarget : null,
        ];
        for (const src of sources) {
            if (!src) continue;
            // URL param forms: _glpi_tab=X or forcetab=X
            let m = src.match(/[?&](?:_glpi_tab|forcetab)=([^&#]+)/);
            if (m) return decodeURIComponent(m[1]);
        }
        // id form like "tab-<rand>-<key>" is not reliable; last resort: title/text none.
        return null;
    }

    function tabAnchors(bar) {
        return Array.from(bar.querySelectorAll('a.nav-link, .nav-item > a, a[data-glpi-ajax-content]'));
    }

    // Console diagnostic — call uxcTabDebug() on any asset page.
    window.uxcTabDebug = function () {
        const bar = tabBar();
        if (!bar) { console.log('[uxc] no .nav-tabs found'); return; }
        const rows = tabAnchors(bar).map((a, i) => ({
            i,
            text: a.textContent.trim().slice(0, 30),
            key: keyOf(a),
            ajax: a.getAttribute('data-glpi-ajax-content'),
            href: a.getAttribute('href'),
        }));
        console.table(rows);
        return rows;
    };

    function apply(order, hidden) {
        const bar = tabBar();
        if (!bar) return;

        const items = Array.from(bar.children).filter(li => li.tagName === 'LI');
        const keyed = items.map(li => ({ li, key: keyOf(li.querySelector('a')) }));
        if (!keyed.some(k => k.key)) return; // couldn't read keys — bail quietly

        // ── Reorder ──
        if (order && order.length) {
            const indexOf = k => { const i = order.indexOf(k); return i === -1 ? Number.MAX_SAFE_INTEGER : i; };
            const sorted = keyed
                .map((entry, origin) => ({ ...entry, origin }))
                .sort((a, b) => (indexOf(a.key) - indexOf(b.key)) || (a.origin - b.origin));
            sorted.forEach(entry => bar.appendChild(entry.li));
        }

        // ── Hide ── (toggle display so a re-run can also un-hide)
        const hide = new Set(hidden || []);
        keyed.forEach(({ li, key }) => {
            li.style.display = (key && hide.has(key)) ? 'none' : '';
        });
    }

    function init() {
        const slug = currentSlug();
        if (!slug || !tabBar()) return; // not an asset form page

        fetch(rootDoc() + '/plugins/uxcustomizer/ajax/taborder.php?action=get&itemtype=' + encodeURIComponent(slug), {
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest' },
        })
            .then(r => r.json())
            .then(resp => { if (resp && resp.ok && resp.data) apply(resp.data.order, resp.data.hidden); })
            .catch(() => { /* ignore — unsupported itemtype returns 400 */ });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
