// admin-report-detail-response.js
// 管理者通報詳細ページ用のJavaScript

(function() {
    'use strict';

    const config = window.adminReportDetailResponseConfig || {};
    const targetResponseId = config.targetResponseId || null;

    // ページ読み込み時に対象レスポンスまでスクロール
    document.addEventListener('DOMContentLoaded', function() {
        if (!targetResponseId) return;

        const responsesContainer = document.getElementById('responses-container');
        const targetElement = responsesContainer?.querySelector(`[data-response-id="${targetResponseId}"]`);
        
        if (targetElement && responsesContainer) {
            // 対象要素の位置を計算（コンテナ内の相対位置）
            const containerRect = responsesContainer.getBoundingClientRect();
            const targetRect = targetElement.getBoundingClientRect();
            const scrollTop = responsesContainer.scrollTop;
            const targetOffsetTop = targetRect.top - containerRect.top + scrollTop;
            
            // 対象レスポンスが中央付近に来るようにスクロール（少し上に余白を取る）
            const scrollPosition = targetOffsetTop - (responsesContainer.clientHeight / 3);
            responsesContainer.scrollTop = Math.max(0, scrollPosition);
        }
    });
})();
