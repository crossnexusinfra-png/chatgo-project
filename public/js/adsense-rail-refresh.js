/**
 * ルーム左右レール: 30 秒ごとにユニットを差し替えて再リクエスト
 */
(function () {
    'use strict';

    var INTERVAL_MS = 30000;

    function replaceRailUnit(wrap) {
        var client = wrap.getAttribute('data-client');
        var slot = wrap.getAttribute('data-slot');
        if (!client || !slot) {
            return;
        }
        wrap.innerHTML = '';
        var ins = document.createElement('ins');
        ins.className = 'adsbygoogle';
        ins.style.display = 'block';
        ins.style.minHeight = '250px';
        ins.setAttribute('data-ad-client', client);
        ins.setAttribute('data-ad-slot', slot);
        ins.setAttribute('data-ad-format', 'vertical');
        ins.setAttribute('data-full-width-responsive', 'false');
        wrap.appendChild(ins);
        try {
            (window.adsbygoogle = window.adsbygoogle || []).push({});
        } catch (e) {
            /* ignore */
        }
    }

    function tick() {
        document.querySelectorAll('[data-adsense-rail-refresh]').forEach(function (wrap) {
            replaceRailUnit(wrap);
        });
    }

    if (document.querySelector('[data-adsense-rail-refresh]')) {
        setInterval(tick, INTERVAL_MS);
    }
})();
