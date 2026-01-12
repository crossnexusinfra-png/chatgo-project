<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Cross-Origin Resource Sharing (CORS) Configuration
    |--------------------------------------------------------------------------
    |
    | CORS設定で、APIエンドポイントへのアクセスを制限します。
    | FRONTEND_URLでフロントエンドのURLを指定します。
    |
    */

    'paths' => [
        'api/*',
        'sanctum/csrf-cookie',
    ],

    'allowed_methods' => [
        'GET',
        'POST',
        'PUT',
        'DELETE',
    ],

    'allowed_origins' => [
        env('FRONTEND_URL', env('APP_URL', 'http://localhost')),
    ],

    'allowed_origins_patterns' => [],

    'allowed_headers' => ['*'],

    'exposed_headers' => [],

    // プリフライトリクエストのキャッシュ時間（1日 = 86400秒）
    // プロジェクト規模を考慮し、パフォーマンスとセキュリティのバランスを取る
    'max_age' => env('CORS_MAX_AGE', 86400),

    'supports_credentials' => true,
];

