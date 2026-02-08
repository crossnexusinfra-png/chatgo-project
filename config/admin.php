<?php

return [
    // 管理者ページの秘密プレフィックス（URLの先頭パス）。ENV未設定時は長い疑似ランダム値。
    'prefix' => env('ADMIN_PREFIX', bin2hex(random_bytes(16))),

    // Basic認証の共有ユーザー名/パスワード（ENVで上書き）
    'user' => env('ADMIN_USER', 'admin'),
    'password' => env('ADMIN_PASSWORD', ''),

    // お知らせテンプレート
    'message_templates' => [
        'maintenance_completed' => [
            'name' => 'システムメンテナンス実施について',
            'title' => 'システムメンテナンス実施について',
            'body' => "いつも当サイトをご利用いただきありがとうございます。\n\nこの度、サービスの安全性向上を目的とした\nシステムメンテナンスを実施いたしました。\n\n本メンテナンスに伴い、一時的にご不便をおかけしましたことを\nお詫び申し上げます。\n\n今後も安心してご利用いただける環境づくりに努めてまいります。",
            'coin_amount' => 3,
            'audience' => 'members', // members または guests
        ],
    ],
];


