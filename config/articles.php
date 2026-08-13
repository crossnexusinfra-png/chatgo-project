<?php

return [
    /*
    |--------------------------------------------------------------------------
    | 公開記事一覧（カテゴリ順）
    |--------------------------------------------------------------------------
    |
    | 各 slug に対応する翻訳キー（resources/lang/{ja,en}.php）:
    |   articles_category_{category}
    |   article_{slug}_title
    |   article_{slug}_summary（任意）
    |   article_{slug}_body（改行区切りの本文）
    |
    */
    'categories' => [
        'getting-started',
        'what-you-can-do',
    ],

    'items' => [
        ['slug' => 'how-to-connect-worldwide', 'category' => 'getting-started'],
        ['slug' => 'create-room-and-ask', 'category' => 'getting-started'],
        ['slug' => 'talk-with-ai-translation', 'category' => 'getting-started'],
        ['slug' => 'everyday-topics-more-interesting', 'category' => 'getting-started'],
        ['slug' => 'travel-with-chatgo', 'category' => 'what-you-can-do'],
        ['slug' => 'language-learning-with-chatgo', 'category' => 'what-you-can-do'],
        ['slug' => 'hobbies-with-chatgo', 'category' => 'what-you-can-do'],
        ['slug' => 'world-lifestyle-with-chatgo', 'category' => 'what-you-can-do'],
        ['slug' => 'ask-the-world-on-chatgo', 'category' => 'what-you-can-do'],
    ],
];
