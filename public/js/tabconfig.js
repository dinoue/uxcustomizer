/* global Sortable */
/**
 * UX Customizer — Tab Order config page: drag-to-reorder an itemtype's tabs,
 * save instantly. No-op unless #uxc-tab-list is present.
 */
(function () {
    'use strict';

    const list = document.getElementById('uxc-tab-list');
    if (!list || !window.UxcConfig) {
        return;
    }

    const status   = document.getElementById('uxc-tab-status');
    const itemtype = list.dataset.itemtype;
    const ajaxUrl  = window.UxcConfig.tabAjax;
    const i18n     = window.UxcConfig.i18n;

    function csrfToken() {
        const m = document.querySelector("meta[property='glpi:csrf_token']");
        return m ? m.getAttribute('content') : '';
    }

    function setStatus(text, kind) {
        if (!status) return;
        status.textContent = text;
        status.className = 'uxc-status uxc-status--' + (kind || 'info');
        if (kind === 'success') {
            setTimeout(() => {
                if (status.textContent === text) { status.textContent = ''; status.className = 'uxc-status'; }
            }, 2000);
        }
    }

    function post(data) {
        const fd = new FormData();
        for (const [k, v] of Object.entries(data)) fd.append(k, v);
        return fetch(ajaxUrl, {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'X-Glpi-Csrf-Token': csrfToken() },
        })
            .then(r => r.json().catch(() => ({ ok: false, error: 'HTTP ' + r.status })))
            .catch(err => ({ ok: false, error: String(err) }));
    }

    function persist() {
        const items  = Array.from(list.querySelectorAll('.uxc-tab-item'));
        const order  = items.map(li => li.dataset.key);
        const hidden = items.filter(li => li.classList.contains('uxc-tab-hidden')).map(li => li.dataset.key);
        setStatus(i18n.saving, 'info');
        post({ action: 'save', itemtype: itemtype, order: JSON.stringify(order), hidden: JSON.stringify(hidden) })
            .then(resp => setStatus(resp.ok ? i18n.saved : i18n.failed + ' ' + (resp.error || ''), resp.ok ? 'success' : 'error'));
    }

    // Hide/unhide toggle (eye icon) — flips state on the row, then saves.
    list.addEventListener('click', e => {
        const btn = e.target.closest('.uxc-tab-eye');
        if (!btn) return;
        const li = btn.closest('.uxc-tab-item');
        if (!li) return;
        const nowHidden = li.classList.toggle('uxc-tab-hidden');
        btn.setAttribute('aria-pressed', nowHidden ? 'true' : 'false');
        const icon = btn.querySelector('i');
        if (icon) { icon.className = 'ti ' + (nowHidden ? 'ti-eye-off' : 'ti-eye'); }
        persist();
    });

    function initSortable() {
        if (typeof Sortable === 'undefined') { return setTimeout(initSortable, 200); }
        Sortable.create(list, {
            handle: '.uxc-handle',
            // The eye button is interactive — tell Sortable to ignore pointer
            // events on it (and NOT preventDefault) so its click isn't swallowed.
            filter: '.uxc-tab-eye, .uxc-tab-eye *',
            preventOnFilter: false,
            animation: 150,
            ghostClass: 'uxc-item--ghost',
            chosenClass: 'uxc-item--chosen',
            onEnd: persist,
        });
    }

    initSortable();
})();
