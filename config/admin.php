<?php

return [
    // 管理者ページのURLパス。ENV未設定時は 'admin'。本番では .env で推測困難な値にすること。
    'prefix' => env('ADMIN_PREFIX', 'admin'),

    // Basic認証の共有ユーザー名/パスワード（ENVで上書き）
    'user' => env('ADMIN_USER', 'admin'),
    'password' => env('ADMIN_PASSWORD', ''),
];


