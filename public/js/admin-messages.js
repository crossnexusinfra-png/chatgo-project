// admin-messages.js
// 管理者メッセージページ用のJavaScript

(function() {
    'use strict';

    // 返信許可チェックボックスの表示/非表示制御
    document.addEventListener('DOMContentLoaded', function() {
        const allowsReplyCheckbox = document.getElementById('allows_reply');
        const replyLimitSection = document.getElementById('reply_limit_section');
        
        if (allowsReplyCheckbox && replyLimitSection) {
            allowsReplyCheckbox.addEventListener('change', function() {
                replyLimitSection.classList.toggle('active', this.checked);
            });
            
            // 初期状態を設定
            replyLimitSection.classList.toggle('active', allowsReplyCheckbox.checked);
        }
    });
})();
