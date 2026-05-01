/**
 * AdSense Auto ads（page-level）の初期化。
 * official モード時のみ push し、独自インタースティシャル実装は利用しない。
 * EEA/UK テスト強制時は簡易同意UIを表示して動作確認できる。
 */
(function () {
    'use strict';
    var CONSENT_KEY = 'chatgo_ads_consent_v1';
    window.__chatgoEeaInitLoaded = true;

    function getMetaContent(name) {
        var el = document.querySelector('meta[name="' + name + '"]');
        return el ? (el.getAttribute('content') || '') : '';
    }

    function parseConfig(metaName) {
        var raw = getMetaContent(metaName);
        if (!raw) return null;
        // HTMLエスケープされた JSON（&quot; など）にも対応
        if (raw.indexOf('&quot;') !== -1 || raw.indexOf('&#34;') !== -1 || raw.indexOf('&amp;') !== -1) {
            var ta = document.createElement('textarea');
            ta.innerHTML = raw;
            raw = ta.value;
        }
        try {
            return JSON.parse(raw);
        } catch (e) {
            return null;
        }
    }

    function readForceEeaUkFallback() {
        try {
            var raw = getMetaContent('adsense-eea-test-config');
            if (!raw) return false;
            if (raw.indexOf('&quot;') !== -1 || raw.indexOf('&#34;') !== -1 || raw.indexOf('&amp;') !== -1) {
                var ta = document.createElement('textarea');
                ta.innerHTML = raw;
                raw = ta.value;
            }
            return /"forceEeaUk"\s*:\s*true/i.test(raw);
        } catch (e) {
            return false;
        }
    }

    function getConsentChoice() {
        try {
            return window.localStorage ? window.localStorage.getItem(CONSENT_KEY) : null;
        } catch (e) {
            return null;
        }
    }

    function setConsentChoice(value) {
        try {
            if (window.localStorage) {
                window.localStorage.setItem(CONSENT_KEY, value);
            }
        } catch (e) {
            /* ignore */
        }
    }

    function removeConsentBanner() {
        var el = document.getElementById('chatgo-eea-consent-banner');
        if (el && el.parentNode) {
            el.parentNode.removeChild(el);
        }
    }

    function initPageLevelAds(client, nonPersonalizedOnly) {
        if (nonPersonalizedOnly) {
            window.adsbygoogle = window.adsbygoogle || [];
            window.adsbygoogle.requestNonPersonalizedAds = 1;
        }
        try {
            (window.adsbygoogle = window.adsbygoogle || []).push({
                google_ad_client: client,
                enable_page_level_ads: true
            });
        } catch (e) {
            /* ignore */
        }
    }

    function renderConsentBanner(onAccept, onReject) {
        removeConsentBanner();

        var banner = document.createElement('div');
        banner.id = 'chatgo-eea-consent-banner';
        banner.setAttribute('role', 'dialog');
        banner.setAttribute('aria-modal', 'false');
        banner.style.position = 'fixed';
        banner.style.left = '12px';
        banner.style.right = '12px';
        banner.style.bottom = '12px';
        banner.style.zIndex = '100001';
        banner.style.background = '#fff';
        banner.style.border = '1px solid #ddd';
        banner.style.borderRadius = '10px';
        banner.style.padding = '12px';
        banner.style.boxShadow = '0 8px 24px rgba(0,0,0,.18)';

        var text = document.createElement('p');
        text.textContent = 'EEA/UK test mode: choose ad consent option for verification.';
        text.style.margin = '0 0 10px 0';
        text.style.fontSize = '14px';
        text.style.lineHeight = '1.4';

        var btnRow = document.createElement('div');
        btnRow.style.display = 'flex';
        btnRow.style.gap = '8px';
        btnRow.style.flexWrap = 'wrap';

        var accept = document.createElement('button');
        accept.type = 'button';
        accept.textContent = 'Accept personalized ads';
        accept.style.padding = '8px 10px';
        accept.style.borderRadius = '6px';
        accept.style.border = '1px solid #2c7';
        accept.style.background = '#2c7';
        accept.style.color = '#fff';
        accept.style.cursor = 'pointer';
        accept.addEventListener('click', onAccept);

        var reject = document.createElement('button');
        reject.type = 'button';
        reject.textContent = 'Reject personalization';
        reject.style.padding = '8px 10px';
        reject.style.borderRadius = '6px';
        reject.style.border = '1px solid #999';
        reject.style.background = '#f7f7f7';
        reject.style.color = '#333';
        reject.style.cursor = 'pointer';
        reject.addEventListener('click', onReject);

        btnRow.appendChild(accept);
        btnRow.appendChild(reject);
        banner.appendChild(text);
        banner.appendChild(btnRow);
        document.body.appendChild(banner);
    }

    function run() {
        var cfg = parseConfig('adsense-page-level-config');
        var eeaCfg = parseConfig('adsense-eea-test-config') || {};
        var forceEeaUk = !!eeaCfg.forceEeaUk || readForceEeaUkFallback();
        window.__chatgoEeaForceFlag = forceEeaUk;
        // テスト強制時は、AdSense有効/official条件の前でも同意UIを表示して検証可能にする
        if (forceEeaUk) {
            var forcedChoice = getConsentChoice();
            if (forcedChoice === 'granted') {
                if (cfg && cfg.enabled && cfg.interstitialMode === 'official' && cfg.client) {
                    initPageLevelAds(cfg.client, false);
                }
                return;
            }
            if (forcedChoice === 'denied') {
                if (cfg && cfg.enabled && cfg.interstitialMode === 'official' && cfg.client) {
                    initPageLevelAds(cfg.client, true);
                }
                return;
            }

            renderConsentBanner(function () {
                setConsentChoice('granted');
                removeConsentBanner();
                if (cfg && cfg.enabled && cfg.interstitialMode === 'official' && cfg.client) {
                    initPageLevelAds(cfg.client, false);
                }
            }, function () {
                setConsentChoice('denied');
                removeConsentBanner();
                if (cfg && cfg.enabled && cfg.interstitialMode === 'official' && cfg.client) {
                    initPageLevelAds(cfg.client, true);
                }
            });

            // テスト強制時に他スクリプトの再描画等でバナーが消えるケースへの保険
            setTimeout(function () {
                if (getConsentChoice() !== null) return;
                if (document.getElementById('chatgo-eea-consent-banner')) return;
                renderConsentBanner(function () {
                    setConsentChoice('granted');
                    removeConsentBanner();
                    if (cfg && cfg.enabled && cfg.interstitialMode === 'official' && cfg.client) {
                        initPageLevelAds(cfg.client, false);
                    }
                }, function () {
                    setConsentChoice('denied');
                    removeConsentBanner();
                    if (cfg && cfg.enabled && cfg.interstitialMode === 'official' && cfg.client) {
                        initPageLevelAds(cfg.client, true);
                    }
                });
            }, 600);
            return;
        }

        if (!cfg || !cfg.enabled) return;
        if (cfg.interstitialMode !== 'official') return;
        if (!cfg.client) return;

        initPageLevelAds(cfg.client, false);
    }

    function safeRun() {
        try {
            run();
            window.__chatgoEeaInitRunOk = true;
        } catch (e) {
            window.__chatgoEeaInitRunOk = false;
            window.__chatgoEeaInitError = (e && e.message) ? e.message : String(e);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', safeRun);
    } else {
        safeRun();
    }
})();
