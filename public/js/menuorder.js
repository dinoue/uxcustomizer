/* global Sortable */
/**
 * UX Customizer — Menu Order drag-to-reorder (config page).
 * Guards on the presence of #uxc-menu-list so it's a no-op on other tabs.
 */
(function () {
    'use strict';

    const list = document.getElementById('uxc-menu-list');
    if (!list || !window.UxcConfig) {
        return;
    }

    const status    = document.getElementById('uxc-menu-status');
    const profileId = parseInt(list.dataset.profileId, 10);
    const ajaxUrl   = window.UxcConfig.menuAjax;
    const i18n      = window.UxcConfig.i18n;

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
        const order = Array.from(list.querySelectorAll('.uxc-menu-item')).map(li => li.dataset.key);
        setStatus(i18n.saving, 'info');
        post({ action: 'save', profiles_id: profileId, order: JSON.stringify(order) })
            .then(resp => setStatus(resp.ok ? i18n.saved : i18n.failed + ' ' + (resp.error || ''), resp.ok ? 'success' : 'error'));
    }

    function initSortable() {
        if (typeof Sortable === 'undefined') { return setTimeout(initSortable, 200); }
        Sortable.create(list, {
            handle: '.uxc-handle',
            animation: 150,
            ghostClass: 'uxc-item--ghost',
            chosenClass: 'uxc-item--chosen',
            onEnd: persist,
        });
    }

    initSortable();
})();
