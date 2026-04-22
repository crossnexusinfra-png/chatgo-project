// external-api-alert.js
// 外部API呼び出し通知を表示

(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        var alertConfig = document.getElementById('external-api-alert-config');
        if (!alertConfig) {
            return;
        }

        var message = alertConfig.getAttribute('data-alert-message') || '';
        if (message) {
            alert(message);
        }
    });
})();
