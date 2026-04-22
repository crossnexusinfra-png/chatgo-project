// error-pages-actions.js
// エラーページの共通アクション

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var goBackButton = document.getElementById('error-page-go-back');
        if (goBackButton) {
            goBackButton.addEventListener('click', function() {
                window.history.back();
            });
        }

        var reloadButton = document.getElementById('error-page-reload');
        if (reloadButton) {
            reloadButton.addEventListener('click', function() {
                window.location.reload();
            });
        }
    });
})();
