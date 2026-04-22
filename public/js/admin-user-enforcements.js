(function() {
    'use strict';

    function parseJsonDataset(value, fallback) {
        if (!value) return fallback;
        try {
            return JSON.parse(value);
        } catch (e) {
            console.error('Failed to parse admin user enforcements config:', e);
            return fallback;
        }
    }

    const configElement = document.getElementById('admin-user-enforcements-config');
    const templates = parseJsonDataset(configElement ? configElement.dataset.templates : '', {});
    const hasOldNotice = configElement ? configElement.dataset.hasOldNotice === '1' : false;

    const typeEl = document.getElementById('enforcement_type_select');
    const durationWrap = document.getElementById('duration_hours_wrap');
    const titleJa = document.getElementById('notice_title_ja');
    const titleEn = document.getElementById('notice_title_en');
    const bodyJa = document.getElementById('notice_body_ja');
    const bodyEn = document.getElementById('notice_body_en');

    function applyTemplate(type) {
        const t = templates[type];
        if (!t) return;
        if (titleJa) titleJa.value = t.title_ja || '';
        if (titleEn) titleEn.value = t.title_en || '';
        if (bodyJa) bodyJa.value = t.body_ja || '';
        if (bodyEn) bodyEn.value = t.body_en || '';
    }

    function sync() {
        if (!typeEl || !durationWrap) return;
        durationWrap.style.display = (typeEl.value === 'restriction' || typeEl.value === 'temporary_freeze') ? 'block' : 'none';
    }

    if (typeEl) {
        typeEl.addEventListener('change', function() {
            sync();
            applyTemplate(typeEl.value);
        });
        sync();
        if (!hasOldNotice) {
            applyTemplate(typeEl.value);
        }
    }
})();
