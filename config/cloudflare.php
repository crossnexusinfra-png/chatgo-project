<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Log Settings
    |--------------------------------------------------------------------------
    |
    | Cloudflareのアクセスログと異常通知の設定
    | 本番環境でのみ有効化される設定
    |
    */

    'enabled' => env('CLOUDFLARE_ENABLED', false),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare API Credentials
    |--------------------------------------------------------------------------
    |
    | Cloudflare APIを使用するための認証情報
    | 本番環境でのみ設定してください
    |
    */

    'api_token' => env('CLOUDFLARE_API_TOKEN'),
    'zone_id' => env('CLOUDFLARE_ZONE_ID'),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Log Retention
    |--------------------------------------------------------------------------
    |
    | ログの保持期間（日数）
    |
    */

    'log_retention_days' => env('CLOUDFLARE_LOG_RETENTION_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Alert Settings
    |--------------------------------------------------------------------------
    |
    | 異常検出時の通知設定
    |
    */

    'alerts' => [
        'enabled' => env('CLOUDFLARE_ALERTS_ENABLED', false),
        'webhook_url' => env('CLOUDFLARE_ALERT_WEBHOOK_URL'),
        'email' => env('CLOUDFLARE_ALERT_EMAIL'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Cloudflare Log Analysis
    |--------------------------------------------------------------------------
    |
    | ログ分析の設定
    |
    */

    'analysis' => [
        'enabled' => env('CLOUDFLARE_ANALYSIS_ENABLED', false),
        'check_interval_minutes' => env('CLOUDFLARE_CHECK_INTERVAL', 5),
        'error_threshold' => env('CLOUDFLARE_ERROR_THRESHOLD', 10), // 5分間で10件以上のエラーでアラート
    ],

];
