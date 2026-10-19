/**
 * Google Ad Manager GPT: data-gpt-slot を定義して display する。
 * refresh は呼ばない（承認後に別途配線する）。
 */
(function () {
    'use strict';

    window.googletag = window.googletag || { cmd: [] };

    googletag.cmd.push(function () {
        var nodes = document.querySelectorAll('[data-gpt-slot]');
        if (!nodes.length) {
            return;
        }

        var defined = {};
        nodes.forEach(function (el) {
            var path = el.getAttribute('data-gpt-path');
            if (!path || !el.id || defined[el.id]) {
                return;
            }
            defined[el.id] = true;
            var sizes = el.getAttribute('data-gpt-size') === 'inline'
                ? [[728, 90], [468, 60], [320, 50], [300, 250]]
                : [[300, 250], [160, 600], [120, 600], [300, 600]];
            googletag.defineSlot(path, sizes, el.id).addService(googletag.pubads());
        });

        googletag.pubads().enableSingleRequest();
        googletag.pubads().collapseEmptyDivs();
        googletag.enableServices();

        Object.keys(defined).forEach(function (id) {
            googletag.display(id);
        });
    });
})();
