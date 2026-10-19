/**
 * ExoClick: data-exoclick-zone に対して AdProvider.serve する。
 */
(function () {
    'use strict';

    function serve(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var zones = scope.querySelectorAll('[data-exoclick-zone]:not([data-chatgo-exo-served])');
        if (!zones.length) {
            return;
        }
        zones.forEach(function (el) {
            el.setAttribute('data-chatgo-exo-served', '1');
        });
        try {
            (window.AdProvider = window.AdProvider || []).push({ serve: {} });
        } catch (e) {
            /* ignore */
        }
    }

    window.chatgoExoclickServe = serve;

    function initOnReady() {
        serve(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnReady);
    } else {
        initOnReady();
    }
})();
