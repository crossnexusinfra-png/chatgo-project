// thread-index.js
// スレッド一覧ページ用のJavaScript

(function() {
    'use strict';

    const config = window.threadIndexConfig || {};
    const translations = config.translations || {};
    const csrfToken = config.csrfToken || '';
    const routes = config.routes || {};
    const adUrls = config.adUrls || {};

    // 広告動画モーダルを動的に生成
    (function setupAdVideoModal() {
        if (document.getElementById('adVideoModal')) return;

        const modal = document.createElement('div');
        modal.id = 'adVideoModal';
        modal.className = 'ad-video-modal';

        let sourcesHtml = '';
        if (adUrls.mainUrl) {
            sourcesHtml += `<source src="${adUrls.mainUrl}" type="video/mp4">`;
        }
        if (adUrls.fallbackUrls && adUrls.fallbackUrls.length > 0) {
            adUrls.fallbackUrls.forEach(url => {
                sourcesHtml += `<source src="${url}" type="video/mp4">`;
            });
        }

        modal.innerHTML = `
            <div class="ad-video-container">
                <button onclick="closeAdVideoFromIndex()" class="ad-video-close-button">${translations.closeButton}</button>
                <video id="adVideoMain" controls class="ad-video-player" preload="auto">
                    ${sourcesHtml}
                    ${translations.videoNotSupported}
                </video>
            </div>
        `;

        document.body.appendChild(modal);
    })();

    window.watchAdFromIndex = function() {
        window.watchAdVideo({
            modalId: 'adVideoModal',
            videoId: 'adVideoMain',
            statusId: 'adWatchStatusMain',
            btnId: 'watchAdBtnMain',
            translations: translations,
            csrfToken: csrfToken,
            watchAdRoute: routes.watchAdRoute || '/coins/watch-ad',
            onSuccess: function(coins) {
                window.playCoinRoulette({
                    overlayId: 'coinRouletteOverlay',
                    valueId: 'coinRouletteValue',
                    messageId: 'coinRouletteMessage',
                    okBtnId: 'coinRouletteOkButton',
                    skipBtnId: 'coinRouletteSkipButton',
                    finalCoins: coins,
                    translations: translations
                });
            },
            onClose: function() {
                window.closeAdVideoFromIndex();
            }
        });
    };

    window.closeAdVideoFromIndex = function() {
        const modal = document.getElementById('adVideoModal');
        const video = document.getElementById('adVideoMain');
        if (!modal || !video) return;
        modal.style.display = 'none';
        video.pause();
        video.currentTime = 0;
    };
})();
