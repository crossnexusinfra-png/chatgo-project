<?php

return [
    // 管理者ページのURLパス。ENV未設定時は 'admin'。本番では .env で推測困難な値にすること。
    'prefix' => env('ADMIN_PREFIX', 'admin'),

    // 互換のため残置（現行の Basic 認証は admins テーブルを参照）
    'user' => env('ADMIN_USER', 'admin'),
    'password' => env('ADMIN_PASSWORD', ''),

    // 初期投入専用（.env -> DB）。通常運用の認証は DB 側で行う。
    'bootstrap' => [
        'enabled' => filter_var(env('ADMIN_BOOTSTRAP_ENABLED', false), FILTER_VALIDATE_BOOL),
        'panel' => [
            'username' => env('ADMIN_PANEL_USERNAME', ''),
            'email' => env('ADMIN_PANEL_EMAIL', ''),
            'password' => env('ADMIN_PANEL_PASSWORD', ''),
        ],
        'user' => [
            'username' => env('USER_ADMIN_USERNAME', ''),
            'identifier' => env('USER_ADMIN_IDENTIFIER', ''),
            'email' => env('USER_ADMIN_EMAIL', ''),
            'password' => env('USER_ADMIN_PASSWORD', ''),
        ],
    ],
];


