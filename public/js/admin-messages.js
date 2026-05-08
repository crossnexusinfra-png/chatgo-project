// admin-messages.js
// 管理者メッセージページ用のJavaScript

(function() {
    'use strict';

    /**
     * 送信先「条件指定」「特定ユーザー」「管理者のみ説明」の表示切替。
     * admin-messages-templates.js がテンプレJSON未取得で早期終了しても必ず動くよう、このファイルで実行する。
     */
    function toggleAdminMessageTargetExtra() {
        const type = document.getElementById('target_type')?.value;
        const filteredEl = document.getElementById('target_filtered_fields');
        const specificEl = document.getElementById('target_specific_fields');
        const adminHint = document.getElementById('target_admin_only_hint');
        if (filteredEl) {
            filteredEl.classList.toggle('is-visible', type === 'filtered');
        }
        if (specificEl) {
            specificEl.classList.toggle('is-visible', type === 'specific');
        }
        if (adminHint) {
            adminHint.classList.toggle('is-visible', type === 'admin_users_only');
        }
        const ri = document.getElementById('recipient_identifiers');
        if (ri) {
            ri.required = (type === 'specific');
        }
    }

    function bindAdminMessageTargetControls() {
        const targetType = document.getElementById('target_type');
        if (!targetType) {
            return;
        }
        targetType.addEventListener('change', toggleAdminMessageTargetExtra);
        toggleAdminMessageTargetExtra();
    }

    document.addEventListener('DOMContentLoaded', function() {
        bindAdminMessageTargetControls();

        const allowsReplyCheckbox = document.getElementById('allows_reply');
        const replyLimitSection = document.getElementById('reply_limit_section');

        if (allowsReplyCheckbox && replyLimitSection) {
            allowsReplyCheckbox.addEventListener('change', function() {
                replyLimitSection.classList.toggle('active', this.checked);
            });

            replyLimitSection.classList.toggle('active', allowsReplyCheckbox.checked);
        }
    });
})();
