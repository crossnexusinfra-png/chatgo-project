<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Google AdSense（表示ユニット）
    |--------------------------------------------------------------------------
    | AdSense 管理画面で作成した広告ユニットの data-ad-slot を .env に設定してください。
    | 未設定のスロットは HTML を出力しません（レイアウトのみの場合は enabled=false）。
    */
    'enabled' => env('ADSENSE_ENABLED', false),

    'client' => env('ADSENSE_CLIENT', 'ca-pub-1438145064622040'),

    'slots' => [
        'display_banner' => env('ADSENSE_SLOT_DISPLAY_BANNER', ''),
        'rail_left' => env('ADSENSE_SLOT_RAIL_LEFT', ''),
        'rail_right' => env('ADSENSE_SLOT_RAIL_RIGHT', ''),
        'interstitial' => env('ADSENSE_SLOT_INTERSTITIAL', ''),
    ],

    /*
    | インタースティシャル表示モード
    | - official: AdSense Auto ads（page-level）を利用
    | - custom  : サイト内の独自オーバーレイ実装を利用
    */
    'interstitial_mode' => env('ADSENSE_INTERSTITIAL_MODE', 'official'),

    /*
    | EEA/UK 同意フローのテスト用強制フラグ
    | true にすると IP/地域判定に関係なく EEA/UK 向け同意UIを表示して検証可能。
    */
    'eea_uk_test_force' => env('ADSENSE_EEA_UK_TEST_FORCE', false),

    /*
    | 一覧グリッドで横長バナーを挟むまでのルーム数（目安: 2列グリッドで約2行＝4）。
    | 最小2。環境変数未設定時は 4。
    */
    'inline_banner_every_n' => max(2, (int) env('ADSENSE_INLINE_BANNER_EVERY_N', 4)),
];
