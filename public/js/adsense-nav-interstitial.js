/**
 * ルーム（/threads/{id}）への遷移: 3回に1回、閉じる操作後に遷移するインタースティシャル風オーバーレイ
 */
(function () {
    'use strict';

    var STORAGE_KEY = 'chatgo_thread_nav_count';
    var memoryCount = 0;
    var COOKIE_MAX_AGE = 60 * 60 * 24 * 365;

    function getStorage() {
        try {
            if (window.localStorage) {
                return window.localStorage;
            }
        } catch (e) {
            /* ignore */
        }
        return window.sessionStorage;
    }

    function getCookieCount() {
        try {
            var m = document.cookie.match(new RegExp('(?:^|; )' + STORAGE_KEY + '=([^;]*)'));
            if (!m) return 0;
            return parseInt(decodeURIComponent(m[1]), 10) || 0;
        } catch (e) {
            return 0;
        }
    }

    function setCookieCount(n) {
        try {
            document.cookie = STORAGE_KEY + '=' + encodeURIComponent(String(n)) + '; path=/; max-age=' + COOKIE_MAX_AGE + '; SameSite=Lax';
        } catch (e) {
            /* ignore */
        }
    }

    function getCount(storage) {
        try {
            return parseInt(storage.getItem(STORAGE_KEY) || '0', 10) || 0;
        } catch (e) {
            var cookieCount = getCookieCount();
            if (cookieCount > 0) return cookieCount;
            return memoryCount;
        }
    }

    function setCount(storage, n) {
        memoryCount = n;
        try {
            storage.setItem(STORAGE_KEY, String(n));
        } catch (e) {
            setCookieCount(n);
            return;
        }
        setCookieCount(n);
    }

    function getMetaContent(name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? el.getAttribute('content') || '' : '';
    }

    function parseConfig() {
        var raw = getMetaContent('adsense-interstitial-config');
        if (!raw) {
            return null;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    var cfg = parseConfig();
    if (!cfg) {
        return;
    }

    function threadPathMatch(pathname) {
        return /\/threads\/\d+\/?$/.test(pathname);
    }

    function buildOverlay(href, closeLabel) {
        var overlay = document.createElement('div');
        overlay.className = 'adsense-interstitial-overlay';
        overlay.setAttribute('role', 'dialog');
        overlay.setAttribute('aria-modal', 'true');

        var inner = document.createElement('div');
        inner.className = 'adsense-interstitial-inner';

        var closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'adsense-interstitial-close btn btn-primary';
        closeBtn.textContent = closeLabel || '閉じる';

        var adWrap = document.createElement('div');
        adWrap.className = 'adsense-interstitial-ad';

        if (cfg.enabled && cfg.client && cfg.slot) {
            var ins = document.createElement('ins');
            ins.className = 'adsbygoogle';
            ins.style.display = 'block';
            ins.setAttribute('data-ad-client', cfg.client);
            ins.setAttribute('data-ad-slot', cfg.slot);
            ins.setAttribute('data-ad-format', 'auto');
            ins.setAttribute('data-full-width-responsive', 'true');
            adWrap.appendChild(ins);
        } else {
            var placeholder = document.createElement('div');
            placeholder.className = 'adsense-interstitial-placeholder';
            placeholder.textContent = '広告';
            adWrap.appendChild(placeholder);
        }
        inner.appendChild(closeBtn);
        inner.appendChild(adWrap);
        overlay.appendChild(inner);

        function go() {
            overlay.remove();
            window.location.href = href;
        }

        closeBtn.addEventListener('click', go);

        closeBtn.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                go();
            }
        });

        document.body.appendChild(overlay);
        if (cfg.enabled && cfg.client && cfg.slot) {
            try {
                (window.adsbygoogle = window.adsbygoogle || []).push({});
            } catch (e) {
                /* ignore */
            }
        }
        closeBtn.focus();
    }

    document.addEventListener('click', function (e) {
        if (e.button && e.button !== 0) {
            return;
        }
        var a = e.target.closest('a[href]');
        if (!a || a.getAttribute('target') === '_blank') {
            return;
        }
        var href = a.getAttribute('href');
        if (!href || href.indexOf('#') === 0) {
            return;
        }
        var url;
        try {
            url = new URL(a.href, window.location.origin);
        } catch (err) {
            return;
        }
        if (url.origin !== window.location.origin) {
            return;
        }
        if (!threadPathMatch(url.pathname)) {
            return;
        }

        var storage = getStorage();
        var n = getCount(storage);
        n += 1;
        setCount(storage, n);

        if (n < 3 || n % 3 !== 0) {
            return;
        }

        e.preventDefault();
        e.stopPropagation();
        if (typeof e.stopImmediatePropagation === 'function') {
            e.stopImmediatePropagation();
        }
        buildOverlay(a.href, cfg.closeLabel);
    }, true);
})();
