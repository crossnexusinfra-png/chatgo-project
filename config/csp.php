<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Content Security Policy
    |--------------------------------------------------------------------------
    |
    | CSPヘッダーを設定します。XSS攻撃を緩和するために有効です。
    |
    */

    'enabled' => env('CSP_ENABLED', true),

    'report_only' => env('CSP_REPORT_ONLY', false),

    'report_uri' => env('CSP_REPORT_URI', null),

    'policies' => [
        'default-src' => [
            "'self'",
        ],
        'script-src' => [
            "'self'",
            "'unsafe-inline'", // onclick 等のインラインイベントハンドラ用（画像プレビュー等）
            // nonceは動的に追加されます
        ],
        'style-src' => [
            "'self'",
            // nonceは動的に追加されます
        ],
        'img-src' => [
            "'self'",
            'data:',
            'blob:',
            'https://flagcdn.com',
        ],
        'font-src' => [
            "'self'",
            'data:',
        ],
        'connect-src' => [
            "'self'",
        ],
        'media-src' => [
            "'self'",
            'blob:',
            'https://commondatastorage.googleapis.com', // 広告動画テスト用
        ],
        'object-src' => [
            "'none'",
        ],
        'base-uri' => [
            "'self'",
        ],
        'form-action' => [
            "'self'",
        ],
        'frame-ancestors' => [
            "'none'",
        ],
    ],
];

