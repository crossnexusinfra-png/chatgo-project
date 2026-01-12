// thread-tag.js
// タグページ用のJavaScript

(function() {
    'use strict';

    const config = window.threadTagConfig || {};
    const tag = config.tag || '';
    const searchQuery = config.searchQuery || '';
    const sortBy = config.sortBy || 'popular';
    const period = config.period || '30';
    const completion = config.completion || 'all';
    const hasMoreThreads = config.hasMoreThreads || false;

    const params = {
        offset: config.currentOffset || 20,
        sort_by: sortBy,
        period: period,
        completion: completion
    };
    if (searchQuery) {
        params.q = searchQuery;
    }

    window.createInfiniteScrollLoader({
        url: `/tag/${tag}/more`,
        params: params,
        hasMore: hasMoreThreads,
        onLoad: function(data, currentOffset) {
            // オフセットは自動的に更新される
        }
    });
})();
