<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'token' => env('POSTMARK_TOKEN'),
    ],

    'resend' => [
        'key' => env('RESEND_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    'veriphone' => [
        'api_key' => env('VERIPHONE_API_KEY'),
        // テスト時のみ: APIキー未設定でも電話番号を許可（本番では true にしないこと）
        'skip_when_no_key' => env('VERIPHONE_SKIP_WHEN_NO_KEY', false),
    ],

    'safebrowsing' => [
        'api_key' => env('SAFEBROWSING_API_KEY'),
    ],

    'openai' => [
        'api_key' => env('OPENAI_API_KEY'),
        // テスト用: 翻訳API呼び出し時にブラウザでアラートを出す（true のときのみ）
        'translation_debug_alert' => env('TRANSLATION_DEBUG_ALERT', false),
    ],

];
