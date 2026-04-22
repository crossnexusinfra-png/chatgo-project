// admin-copy-btn.js
// 管理画面のコピーボタン共通処理

(function() {
    'use strict';

    document.addEventListener('click', function(e) {
        const btn = e.target.closest('.admin-copy-btn');
        if (!btn) {
            return;
        }

        const text = btn.getAttribute('data-copy-text') || '';
        if (!text || !navigator.clipboard) {
            return;
        }

        const originalText = btn.textContent;
        const copiedLabel = btn.getAttribute('data-copied-label') || originalText;

        navigator.clipboard.writeText(text).then(function() {
            btn.textContent = copiedLabel;
            setTimeout(function() {
                btn.textContent = originalText;
            }, 1200);
        });
    });
})();
