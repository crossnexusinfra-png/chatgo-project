/**
 * カテゴリ・タグ・検索の一覧に、トップと同じ横長バナーを3行ごとに置く。
 * 列数は画面幅で変わるので、カードの並びから1行の件数を数える。
 */
(function () {
    'use strict';

    var ROWS_PER_AD = 3;
    var serial = 0;

    function columnsIn(cards) {
        var firstTop = cards[0].offsetTop;
        var count = 0;
        for (var i = 0; i < cards.length; i++) {
            if (Math.abs(cards[i].offsetTop - firstTop) > 4) {
                break;
            }
            count++;
        }
        return count > 0 ? count : 1;
    }

    function uniquify(ad) {
        serial += 1;
        var slot = ad.querySelector('[data-gpt-slot]');
        if (slot) {
            slot.id = 'gpt-list-row-' + serial;
        }
    }

    function discard(ad) {
        if (typeof window.chatgoGptDestroy === 'function') {
            window.chatgoGptDestroy(ad);
        }
        ad.remove();
    }

    function serve(ad) {
        if (typeof window.chatgoGptServe === 'function') {
            window.chatgoGptServe(ad);
        }
        if (typeof window.chatgoArrowServe === 'function') {
            window.chatgoArrowServe(ad);
        }
    }

    function isPlaced(grid, every) {
        var seen = 0;
        var children = grid.children;
        for (var i = 0; i < children.length; i++) {
            var el = children[i];
            if (!el.classList || !el.classList.contains('post-item')) {
                continue;
            }
            seen++;
            var next = children[i + 1];
            var nextIsAd = !!(next && next.classList && next.classList.contains('posts-grid-ad-row'));
            if ((seen % every === 0) !== nextIsAd) {
                return false;
            }
        }
        return seen > 0;
    }

    function place(grid) {
        var template = document.getElementById('list-row-ad-template');
        if (!grid || !template || !template.content || !template.content.firstElementChild) {
            return;
        }

        var cards = Array.prototype.filter.call(grid.children, function (el) {
            return el.classList && el.classList.contains('post-item');
        });
        if (!cards.length) {
            return;
        }

        var every = columnsIn(cards) * ROWS_PER_AD;
        if (isPlaced(grid, every)) {
            return;
        }

        var existing = Array.prototype.slice.call(grid.querySelectorAll(':scope > .posts-grid-ad-row'));
        existing.forEach(function (ad) {
            ad.remove();
        });
        var indicator = grid.querySelector(':scope > .loading-indicator');
        var used = 0;
        var fresh = [];

        function put(node) {
            if (indicator) {
                grid.insertBefore(node, indicator);
            } else {
                grid.appendChild(node);
            }
        }

        cards.forEach(function (card, index) {
            put(card);
            if ((index + 1) % every !== 0) {
                return;
            }
            var ad = existing[used] || null;
            if (!ad) {
                ad = template.content.firstElementChild.cloneNode(true);
                uniquify(ad);
                fresh.push(ad);
            }
            put(ad);
            used++;
        });

        for (var i = used; i < existing.length; i++) {
            discard(existing[i]);
        }
        fresh.forEach(serve);
    }

    window.chatgoPlaceListRowAds = place;

    function watch(grid) {
        if (typeof ResizeObserver !== 'function') {
            place(grid);
            return;
        }
        var observer = new ResizeObserver(function () {
            var width = grid.clientWidth;
            if (!width || grid.getAttribute('data-ad-grid-width') === String(width)) {
                return;
            }
            grid.setAttribute('data-ad-grid-width', String(width));
            place(grid);
        });
        observer.observe(grid);
    }

    function init() {
        if (!document.getElementById('list-row-ad-template')) {
            return;
        }
        document.querySelectorAll('.posts-grid').forEach(watch);
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

    window.addEventListener('load', function () {
        document.querySelectorAll('.posts-grid').forEach(place);
    });
})();
