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
];
