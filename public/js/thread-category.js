// thread-category.js
// カテゴリーページ用のJavaScript

(function() {
    'use strict';

    const configElement = document.getElementById('thread-category-config');
    const config = {
        category: configElement ? (configElement.dataset.category || '') : '',
        sortBy: configElement ? (configElement.dataset.sortBy || 'latest') : 'latest',
        period: configElement ? (configElement.dataset.period || '') : '',
        completion: configElement ? (configElement.dataset.completion || 'all') : 'all',
        hasMoreThreads: configElement ? configElement.dataset.hasMoreThreads === '1' : false,
        currentOffset: configElement ? parseInt(configElement.dataset.currentOffset || '20', 10) : 20
    };
    const category = config.category || '';
    const sortBy = config.sortBy || 'latest';
    const period = config.period || '';
    const completion = config.completion || 'all';
    const hasMoreThreads = config.hasMoreThreads || false;

    window.createInfiniteScrollLoader({
        url: `/api/category/${category}/more`,
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
