(function() {
    'use strict';

    function setInputValue(id, value) {
        const input = document.getElementById(id);
        if (input) {
            input.value = value;
        }
    }

    function resetCorrelationFiltersExceptLines() {
        setInputValue('search', '');
        setInputValue('request_id', '');
        setInputValue('event_id', '');
        setInputValue('status_code', '');
    }

    function openLogFilePanel() {
        const panel = document.getElementById('adminLogFilePanel');
        const toggle = document.querySelector('[data-target-id="adminLogFilePanel"]');
        if (panel && toggle && !panel.classList.contains('is-open')) {
            panel.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    function openWalPanel() {
        const panel = document.getElementById('adminWalLogsPanel');
        const toggle = document.querySelector('[data-target-id="adminWalLogsPanel"]');
        if (panel && toggle && !panel.classList.contains('is-open')) {
            panel.classList.add('is-open');
            toggle.setAttribute('aria-expanded', 'true');
        }
    }

    function initCorrelationFilterClick() {
        const form = document.getElementById('adminLogsFilterForm');
        if (!form) {
            return;
        }

        document.querySelectorAll('.js-log-filter').forEach(function(button) {
            button.addEventListener('click', function() {
                const filterType = button.dataset.filterType;
                const filterValue = button.dataset.filterValue || '';
                if (!filterValue || filterValue === '-') {
                    return;
                }

                // 相関IDクリック時は既存条件とのANDを避けるため、
                // 行数指定以外のフィルタをいったんクリアしてから設定する。
                resetCorrelationFiltersExceptLines();

                if (filterType === 'request_id') {
                    setInputValue('request_id', filterValue);
                } else if (filterType === 'event_id') {
                    setInputValue('event_id', filterValue);
                } else if (filterType === 'status_code') {
                    setInputValue('status_code', filterValue);
                } else {
                    return;
                }

                openLogFilePanel();
                openWalPanel();
                form.submit();
            });
        });
    }

    function initCollapsiblePanels() {
        document.querySelectorAll('.admin-collapsible-toggle').forEach(function(toggleBtn) {
            toggleBtn.addEventListener('click', function() {
                const panel = document.getElementById(toggleBtn.dataset.targetId);
                if (!panel) {
                    return;
                }

                const isOpen = panel.classList.toggle('is-open');
                toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            });
        });
    }

    function initialize() {
        initCollapsiblePanels();
        initCorrelationFilterClick();
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initialize);
    } else {
        initialize();
    }
})();
