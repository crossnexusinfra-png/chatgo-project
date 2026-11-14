/**
 * バナー列の実幅。
 * トップはタグ一覧（.sidebar）が出ているときだけその外寸と gap を引く。
 * 検索・タグ・カテゴリはサイドバーが無いので、main-container の左右 padding だけを引く。
 * 幅の分岐は CSS の media query と同じ matchMedia を使う。
 * 引き算の基準はスクロールバーを除いた documentElement.clientWidth。
 */
(function () {
    'use strict';

    var BANNERS = [
        { key: 'l', size: [728, 90] },
        { key: 'm', size: [468, 60] },
        { key: 's', size: [320, 50] },
    ];

    function layoutWidth() {
        return document.documentElement.clientWidth || window.innerWidth || 0;
    }

    function matches(query) {
        return window.matchMedia(query).matches;
    }

    function mainPadding() {
        if (matches('(max-width: 768px)')) {
            return 30;
        }
        if (matches('(min-width: 1400px)')) {
            return 60;
        }
        if (matches('(max-width: 1200px)')) {
            return 30;
        }
        return 40;
    }

    function sidebarOuter() {
        if (matches('(min-width: 1400px)')) {
            return 340;
        }
        if (matches('(max-width: 1200px)')) {
            return 290;
        }
        return 320;
    }

    function columnGap() {
        if (matches('(min-width: 1400px)')) {
            return 40;
        }
        if (matches('(max-width: 1200px)')) {
            return 20;
        }
        return 30;
    }

    function tagSidebarOpen() {
        return !!document.querySelector('.main-container > .sidebar')
            && !matches('(max-width: 768px)');
    }

    window.chatgoBannerColumnWidth = function (el) {
        if (el && el.closest && el.closest('.thread-show-outer')) {
            return Math.max(0, Math.min(800, layoutWidth() - 32));
        }
        var width = layoutWidth() - mainPadding();
        if (tagSidebarOpen()) {
            width -= sidebarOuter() + columnGap();
        }
        return Math.max(0, width);
    };

    window.chatgoBannerChoice = function (el) {
        var width = window.chatgoBannerColumnWidth(el);
        for (var i = 0; i < BANNERS.length; i++) {
            if (BANNERS[i].size[0] <= width) {
                return BANNERS[i];
            }
        }
        return null;
    };

    window.chatgoFittingInlineSizes = function (el) {
        var choice = window.chatgoBannerChoice(el);
        return choice ? [choice.size] : [];
    };

    window.chatgoSyncBannerBox = function (el, hidden) {
        var width = window.chatgoBannerColumnWidth(el);
        var box = el.closest
            ? (el.closest('.posts-grid-ad-row, .main-page-ad-slot, .adsense-thread-reply-inline')
                || el.closest('.ad-slot--inline'))
            : null;
        if (!box) {
            return width;
        }
        box.style.maxWidth = width + 'px';
        box.setAttribute('data-ad-column', String(width));
        if (typeof hidden === 'boolean') {
            box.classList.toggle('is-ad-unfit', hidden);
        }
        return width;
    };
})();
