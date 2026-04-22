// thread-search.js
// ルーム検索ページ用のJavaScript

(function() {
    'use strict';

    const configElement = document.getElementById('thread-search-config');
    const config = {
        query: configElement ? (configElement.dataset.query || '') : '',
        sortBy: configElement ? (configElement.dataset.sortBy || 'latest') : 'latest',
        period: configElement ? (configElement.dataset.period || '') : '',
        completion: configElement ? (configElement.dataset.completion || 'all') : 'all',
        hasMoreThreads: configElement ? configElement.dataset.hasMoreThreads === '1' : false,
        currentOffset: configElement ? parseInt(configElement.dataset.currentOffset || '20', 10) : 20
    };
    const query = config.query || '';
    const sortBy = config.sortBy || 'latest';
    const period = config.period || '';
    const completion = config.completion || 'all';
    const hasMoreThreads = config.hasMoreThreads || false;

    window.createInfiniteScrollLoader({
        url: '/api/search/more',
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
