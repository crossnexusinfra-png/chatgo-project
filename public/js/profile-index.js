// profile-index.js
// プロフィールページ用のJavaScript

(function() {
    'use strict';

    const config = window.profileIndexConfig || {};
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
        window.initLoadMoreButton({
            buttonId: 'load-more-threads',
            listId: 'threads-list',
            getUrl: function(offset) {
                return `/profile/threads/more?offset=${offset}`;
            },
            translations: translations
        });
    });
})();
