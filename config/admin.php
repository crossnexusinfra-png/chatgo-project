<?php

return [
    // 管理者ページの秘密プレフィックス（URLの先頭パス）。ENV未設定時は長い疑似ランダム値。
    'prefix' => env('ADMIN_PREFIX', bin2hex(random_bytes(16))),

    // Basic認証の共有ユーザー名/パスワード（ENVで上書き）
    'user' => env('ADMIN_USER', 'admin'),
    'password' => env('ADMIN_PASSWORD', ''),
];


