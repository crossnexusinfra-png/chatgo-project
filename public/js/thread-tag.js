// thread-tag.js
// タグページ用のJavaScript

(function() {
    'use strict';

    const configElement = document.getElementById('thread-tag-config');
    const config = {
        tag: configElement ? (configElement.dataset.tag || '') : '',
        searchQuery: configElement ? (configElement.dataset.searchQuery || '') : '',
        sortBy: configElement ? (configElement.dataset.sortBy || 'popular') : 'popular',
        period: configElement ? (configElement.dataset.period || '30') : '30',
        completion: configElement ? (configElement.dataset.completion || 'all') : 'all',
        hasMoreThreads: configElement ? configElement.dataset.hasMoreThreads === '1' : false,
        currentOffset: configElement ? parseInt(configElement.dataset.currentOffset || '20', 10) : 20
    };
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
        url: `/api/tag/${tag}/more`,
        params: params,
        hasMore: hasMoreThreads,
        onLoad: function(data, currentOffset) {
            // オフセットは自動的に更新される
        }
    });
})();
