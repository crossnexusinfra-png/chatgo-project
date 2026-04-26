(function() {
    'use strict';

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

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initCollapsiblePanels);
    } else {
        initCollapsiblePanels();
    }
})();
