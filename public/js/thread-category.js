// thread-category.js
// カテゴリーページ用のJavaScript

(function() {
    'use strict';

    const config = window.threadCategoryConfig || {};
    const category = config.category || '';
    const sortBy = config.sortBy || 'latest';
    const period = config.period || '';
    const completion = config.completion || 'all';
    const hasMoreThreads = config.hasMoreThreads || false;

    window.createInfiniteScrollLoader({
        url: `/category/${category}/more`,
        params: {
            offset: config.currentOffset || 20,
            sort_by: sortBy,
            period: period,
            completion: completion
        },
        hasMore: hasMoreThreads,
        onLoad: function(data, currentOffset) {
            // オフセットは自動的に更新される
        }
    });
})();
