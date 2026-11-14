/**
 * ARROW のバナーは列幅に合う S / M / L の1枠だけを iframe で読む。
 * 公式タグは document.write を使うため、親ページには置かない。
 */
(function () {
    'use strict';

    function frame(src, width, height) {
        var iframe = document.createElement('iframe');
        iframe.className = 'arrow-slot';
        iframe.setAttribute('scrolling', 'no');
        iframe.setAttribute('title', 'ARROW');
        iframe.setAttribute('referrerpolicy', 'strict-origin-when-cross-origin');
        iframe.style.width = width + 'px';
        iframe.style.height = height + 'px';
        iframe.style.border = '0';
        iframe.style.display = 'block';
        iframe.src = src;
        return iframe;
    }

    function serveInline(el) {
        if (!window.chatgoBannerChoice || !window.chatgoSyncBannerBox) {
            return;
        }
        var choice = window.chatgoBannerChoice(el);
        var src = choice ? (el.getAttribute('data-arrow-src-' + choice.key) || '') : '';
        var hidden = !choice || src === '';
        window.chatgoSyncBannerBox(el, hidden);
        var sig = hidden ? '' : (choice.key + ' ' + src);
        if ((el.getAttribute('data-arrow-sig') || '') === sig) {
            return;
        }
        el.setAttribute('data-arrow-sig', sig);
        el.querySelectorAll('iframe.arrow-slot').forEach(function (node) {
            node.remove();
        });
        if (!hidden) {
            el.appendChild(frame(src, choice.size[0], choice.size[1]));
        }
    }

    function serve(root) {
        var scope = root && root.querySelectorAll ? root : document;
        scope.querySelectorAll('[data-arrow-inline]').forEach(serveInline);
    }

    window.chatgoArrowServe = serve;

    function initOnReady() {
        serve(document);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initOnReady);
    } else {
        initOnReady();
    }

    var relayoutTimer = 0;
    window.addEventListener('resize', function () {
        window.clearTimeout(relayoutTimer);
        relayoutTimer = window.setTimeout(function () {
            serve(document);
        }, 200);
    });
})();
