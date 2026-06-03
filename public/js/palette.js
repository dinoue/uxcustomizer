/**
 * UX Customizer — Color Palette config (live hex labels + AJAX save/reset).
 * Guards on #uxc-palette-form so it's a no-op on other tabs.
 */
(function () {
    'use strict';

    const form = document.getElementById('uxc-palette-form');
    if (!form || !window.UxcConfig) {
        return;
    }

    const status  = document.getElementById('uxc-palette-status');
    const ajaxUrl = window.UxcConfig.paletteAjax;
    const i18n    = window.UxcConfig.i18n;

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

    // Live hex label next to each color input.
    form.querySelectorAll('.uxc-color').forEach(input => {
        input.addEventListener('input', () => {
            const code = input.closest('.row').querySelector('.uxc-hex');
            if (code) code.textContent = input.value;
        });
    });

    // Collect the palette object from the color inputs.
    function collect() {
        const out = {};
        form.querySelectorAll('.uxc-color').forEach(input => { out[input.name] = input.value; });
        return out;
    }

    function paletteName() {
        const el = document.getElementById('uxc-palette-name');
        return el ? el.value : '';
    }

    function darkEnabled() {
        const el = document.getElementById('uxc-palette-dark');
        return el && el.checked ? '1' : '';
    }

    form.addEventListener('submit', e => {
        e.preventDefault();
        setStatus(i18n.saving, 'info');
        post({
            action:       'save',
            palette_name: paletteName(),
            palette_dark: darkEnabled(),
            palette:      JSON.stringify(collect()),
        }).then(resp => setStatus(resp.ok ? i18n.saved : i18n.failed + ' ' + (resp.error || ''), resp.ok ? 'success' : 'error'));
    });

    const resetBtn = document.getElementById('uxc-palette-reset');
    if (resetBtn) {
        resetBtn.addEventListener('click', () => {
            setStatus(i18n.saving, 'info');
            post({ action: 'reset' }).then(resp => {
                const pal = resp.data && resp.data.palette;
                if (resp.ok && pal) {
                    // Reflect restored defaults in the name + color inputs.
                    const nameEl = document.getElementById('uxc-palette-name');
                    if (nameEl && pal.name) nameEl.value = pal.name;
                    Object.entries(pal.colors || {}).forEach(([k, v]) => {
                        const input = form.querySelector('.uxc-color[name="' + k + '"]');
                        if (input) {
                            input.value = v;
                            const code = input.closest('.row').querySelector('.uxc-hex');
                            if (code) code.textContent = v;
                        }
                    });
                    setStatus(i18n.saved, 'success');
                } else {
                    setStatus(i18n.failed + ' ' + (resp.error || ''), 'error');
                }
            });
        });
    }
})();
