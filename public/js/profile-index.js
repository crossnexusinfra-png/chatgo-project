// profile-index.js
// プロフィールページ用のJavaScript

(function() {
    'use strict';

    function parseJsonDataset(value, fallback) {
        if (!value) return fallback;
        try {
            return JSON.parse(value);
        } catch (e) {
            console.error('Failed to parse profile index config dataset:', e);
            return fallback;
        }
    }

    const configElement = document.getElementById('profile-index-config');
    const config = configElement ? {
        lang: configElement.dataset.lang || 'ja',
        userId: configElement.dataset.userId ? parseInt(configElement.dataset.userId, 10) : null,
        translations: parseJsonDataset(configElement.dataset.translations, {}),
        countries: parseJsonDataset(configElement.dataset.countries, {})
    } : (window.profileIndexConfig || {});
    const lang = config.lang || 'ja';
    const translations = config.translations || {};
    const userId = config.userId || null;

    function getCountryName(code) {
        const countries = config.countries || {};
        return countries[code] || code;
    }

    window.openResidenceHistoryModal = function(userId) {
        window.openResidenceHistoryModalCommon({
            modalId: 'residenceHistoryModal',
            contentId: 'historyContent',
            userId: userId,
            countries: config.countries || {},
            getCountryName: getCountryName,
            translations: translations
        });
    };

    window.closeResidenceHistoryModal = function() {
        document.getElementById('residenceHistoryModal').style.display = 'none';
    };

    window.openCoinRewardModal = function() {
        const modal = document.getElementById('coinRewardModal');
        modal.style.display = 'block';
    };

    window.closeCoinRewardModal = function() {
        document.getElementById('coinRewardModal').style.display = 'none';
    };

    // モーダルの外側をクリックしたときに閉じる
    window.onclick = function(event) {
        const residenceModal = document.getElementById('residenceHistoryModal');
        const coinModal = document.getElementById('coinRewardModal');
        if (event.target == residenceModal) {
            residenceModal.style.display = 'none';
        }
        if (event.target == coinModal) {
            coinModal.style.display = 'none';
        }
    };

    // さらに表示ボタンの処理
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(e) {
            const openResidenceBtn = e.target.closest('[data-action="open-residence-history-modal"]');
            if (openResidenceBtn) {
                e.preventDefault();
                const targetUserId = parseInt(openResidenceBtn.getAttribute('data-user-id') || String(userId || ''), 10);
                if (!isNaN(targetUserId)) {
                    window.openResidenceHistoryModal(targetUserId);
                }
                return;
            }
            const closeResidenceBtn = e.target.closest('[data-action="close-residence-history-modal"]');
            if (closeResidenceBtn) {
                e.preventDefault();
                window.closeResidenceHistoryModal();
                return;
            }
            const openCoinBtn = e.target.closest('[data-action="open-coin-reward-modal"]');
            if (openCoinBtn) {
                e.preventDefault();
                window.openCoinRewardModal();
                return;
            }
            const closeCoinBtn = e.target.closest('[data-action="close-coin-reward-modal"]');
            if (closeCoinBtn) {
                e.preventDefault();
                window.closeCoinRewardModal();
            }
        });

        window.initLoadMoreButton({
            buttonId: 'load-more-threads',
            listId: 'threads-list',
            getUrl: function(offset) {
                return `/api/profile/threads/more?offset=${offset}`;
            },
            translations: translations
        });
    });
})();
