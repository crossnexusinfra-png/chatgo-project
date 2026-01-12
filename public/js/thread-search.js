// thread-search.js
// スレッド検索ページ用のJavaScript

(function() {
    'use strict';

    const config = window.threadSearchConfig || {};
    const query = config.query || '';
    const sortBy = config.sortBy || 'latest';
    const period = config.period || '';
    const completion = config.completion || 'all';
    const hasMoreThreads = config.hasMoreThreads || false;

    window.createInfiniteScrollLoader({
        url: '/search/more',
        params: {
            offset: config.currentOffset || 20,
            q: query,
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
