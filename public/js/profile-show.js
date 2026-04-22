// profile-show.js
// プロフィール表示ページ用のJavaScript

(function() {
    'use strict';

    function parseJsonDataset(value, fallback) {
        if (!value) return fallback;
        try {
            return JSON.parse(value);
        } catch (e) {
            console.error('Failed to parse profile show config dataset:', e);
            return fallback;
        }
    }

    const configElement = document.getElementById('profile-show-config');
    const config = configElement ? {
        lang: configElement.dataset.lang || 'ja',
        translations: parseJsonDataset(configElement.dataset.translations, {}),
        countries: parseJsonDataset(configElement.dataset.countries, {})
    } : (window.profileShowConfig || {});
    const lang = config.lang || 'ja';
    const translations = config.translations || {};
    const countries = config.countries || {};

    function getCountryName(code) {
        return countries[code] || code;
    }

    window.openResidenceHistoryModal = function(userId) {
        window.openResidenceHistoryModalCommon({
            modalId: 'residenceHistoryModal',
            contentId: 'historyContent',
            userId: userId,
            countries: countries,
            getCountryName: getCountryName,
            translations: translations
        });
    };

    window.closeResidenceHistoryModal = function() {
        const modal = document.getElementById('residenceHistoryModal');
        if (modal) {
            modal.style.display = 'none';
        }
    };

    // モーダルの外側をクリックしたときに閉じる
    window.onclick = function(event) {
        const modal = document.getElementById('residenceHistoryModal');
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    };

    // さらに表示ボタンの処理
    document.addEventListener('DOMContentLoaded', function() {
        document.addEventListener('click', function(e) {
            const openResidenceBtn = e.target.closest('[data-action="open-residence-history-modal"]');
            if (openResidenceBtn) {
                e.preventDefault();
                const targetUserId = parseInt(openResidenceBtn.getAttribute('data-user-id') || '', 10);
                if (!isNaN(targetUserId)) {
                    window.openResidenceHistoryModal(targetUserId);
                }
                return;
            }
            const closeResidenceBtn = e.target.closest('[data-action="close-residence-history-modal"]');
            if (closeResidenceBtn) {
                e.preventDefault();
                window.closeResidenceHistoryModal();
            }
        });

        window.initLoadMoreButton({
            buttonId: 'load-more-threads',
            listId: 'threads-list',
            getUrl: function(offset) {
                const loadMoreBtn = document.getElementById('load-more-threads');
                const userId = loadMoreBtn ? loadMoreBtn.getAttribute('data-user-id') : null;
                return userId ? `/api/user/${userId}/threads/more?offset=${offset}` : `/api/profile/threads/more?offset=${offset}`;
            },
            translations: translations
        });
    });
})();
