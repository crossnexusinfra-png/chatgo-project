// profile-show.js
// プロフィール表示ページ用のJavaScript

(function() {
    'use strict';

    const config = window.profileShowConfig || {};
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
        window.initLoadMoreButton({
            buttonId: 'load-more-threads',
            listId: 'threads-list',
            getUrl: function(offset) {
                const loadMoreBtn = document.getElementById('load-more-threads');
                const userId = loadMoreBtn ? loadMoreBtn.getAttribute('data-user-id') : null;
                return userId ? `/user/${userId}/threads/more?offset=${offset}` : `/profile/threads/more?offset=${offset}`;
            },
            translations: translations
        });
    });
})();
