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
            'https://pagead2.googlesyndication.com', // Google AdSense
            // nonceは動的に追加されます
        ],
        'style-src' => [
            "'self'",
            "'unsafe-inline'", // Google AdSense のスクリプトがインライン style を注入するため（審査・表示に必要）
            // nonceは動的に追加されます
        ],
        'img-src' => [
            "'self'",
            'data:',
            'blob:',
            'https://flagcdn.com',
            'https://tpc.googlesyndication.com',
            'https://googleads.g.doubleclick.net',
            'https://www.googletagservices.com',
            'https://www.google.com',
        ],
        'font-src' => [
            "'self'",
            'data:',
        ],
        'connect-src' => [
            "'self'",
            'https://pagead2.googlesyndication.com',
            'https://googleads.g.doubleclick.net',
            'https://tpc.googlesyndication.com',
            'https://ep1.adtrafficquality.google',
            'https://csi.gstatic.com', // Google AdSense 計測・CSI 用
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
        'frame-src' => [
            'https://tpc.googlesyndication.com',
            'https://googleads.g.doubleclick.net',
            'https://fundingchoicesmessages.google.com',
            'https://*.google.com',
            'https://*.adtrafficquality.google', // Google AdSense 広告品質 iframe 用（ep1, ep2 等）
        ],
        'frame-ancestors' => [
            "'none'",
        ],
    ],
];

