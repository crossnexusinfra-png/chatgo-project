// common-header.js
// ヘッダー用のJavaScript（共通）

(function() {
    'use strict';

    const config = window.headerConfig || {};
    const translations = config.translations || {};

    // 検索フォームのバリデーション
    document.addEventListener('DOMContentLoaded', function() {
        const searchForm = document.getElementById('searchForm');
        if (searchForm) {
            searchForm.addEventListener('submit', function(e) {
                const searchInput = document.getElementById('searchInput');
                if (searchInput) {
                    const query = searchInput.value.trim();
                    
                    // 空白のみや空文字の場合は送信を防ぐ
                    if (!query || query.length === 0) {
                        e.preventDefault();
                        if (translations.search) {
                            alert(translations.search);
                        }
                        searchInput.focus();
                        return false;
                    }
                }
                
                return true;
            });
        }
    });
})();
