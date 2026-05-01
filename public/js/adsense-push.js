/**
 * AdSense: 動的挿入された ins.adsbygoogle に push する（同一ページの多重 push 防止）
 */
(function () {
    'use strict';

    function pushIn(root) {
        var scope = root && root.querySelectorAll ? root : document;
        var units = scope.querySelectorAll('ins.adsbygoogle:not([data-chatgo-pushed])');
        units.forEach(function (ins) {
            ins.setAttribute('data-chatgo-pushed', '1');
            try {
                (window.adsbygoogle = window.adsbygoogle || []).push({});
            } catch (e) {
                /* ignore */
            }
        });
    }

    window.chatgoAdsensePushIn = pushIn;

    function initOnReady() {
        pushIn(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnReady);
    } else {
        initOnReady();
    }
})();
