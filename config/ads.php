<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Ad Manager Configuration
    |--------------------------------------------------------------------------
    |
    | Google AdManagerのミディエーション設定
    | テスト環境ではテスト用URLを使用してください
    |
    */

    // コイン獲得を出す。GAM_REWARDED_UNIT があるときは Ad Manager のリワード、無いときはテスト動画。
    'enabled' => (bool) env('ADS_ENABLED', false),

    // ads.txt に追記する追加行（GAM 管理画面の追加 seller 行など。改行区切り）
    'ads_txt_extra' => env('ADS_TXT_EXTRA', ''),

    // 広告動画のテスト用URL（開発・テスト環境用）
    // 5秒程度の短いテスト用動画を使用（本番では環境変数で上書きしてください）
    // 注意: デフォルトURLはテスト用です。本番環境では適切な広告動画URLを設定してください
    'test_ad_url' => env('AD_TEST_URL', 'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerBlazes.mp4'),

    // フォールバック用の動画URL（複数指定可能、5秒程度の短い動画）
    // 最初のURLが読み込めない場合、順番に次のURLを試します
    'test_ad_fallback_urls' => [
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerEscapes.mp4',
        'https://commondatastorage.googleapis.com/gtv-videos-bucket/sample/ForBiggerFun.mp4',
    ],

    // 本番環境のGoogle AdManager設定（アプリ向け。Web GPT は ads.gam を使う）
    'ad_manager_publisher_id' => env('AD_MANAGER_PUBLISHER_ID', ''),
    'ad_manager_app_id' => env('AD_MANAGER_APP_ID', ''),
    'ad_manager_ad_unit_id' => env('AD_MANAGER_AD_UNIT_ID', ''),

    /*
    | Google Ad Manager (GPT / ウェブ)。
    | 一般向けページは GPT だけを読む。AdSense は GAM のバックフィルで配信する。
    | ページに adsbygoogle.js は出さない。
    | R18 は Google のポリシー上 GAM に載せられないため、ARROW の直タグを使う。
    | enabled=false またはスロットパスが空のときは枠のみ（審査中の既定）。
    | 左右レールは同じ広告ユニットを複数枠で使う。
    | レールの定期 refresh は未配線。リワードは視聴完了後の再取得だけ行う。
    */
    'gam' => [
        'enabled' => (bool) env('GAM_ENABLED', false),
        'network_code' => env('GAM_NETWORK_CODE', ''),
        'slots' => [
            'rail' => env('GAM_SLOT_RAIL', env('GAM_SLOT_RAIL_LEFT', '')),
            'banner_l' => env('GAM_SLOT_BANNER_L', ''),
            'banner_m' => env('GAM_SLOT_BANNER_M', ''),
            'banner_s' => env('GAM_SLOT_BANNER_S', ''),
            'inline' => env('GAM_SLOT_INLINE', ''),
            'interstitial' => env('GAM_SLOT_INTERSTITIAL', ''),
            'rewarded' => env('GAM_REWARDED_UNIT', ''),
        ],
        // 予約。30 未満では使わない。現在の JS は定期 refresh しない。
        'refresh_seconds' => (int) env('GAM_RAIL_REFRESH_SECONDS', 0),
        'rewarded_unit' => env('GAM_REWARDED_UNIT', ''),
    ],

    /*
    | ARROW の直タグ。R18 ルーム、R18 タグ、R18 を含む検索だけに出す。
    | バナーは列幅に合う1枠だけを読む。S=320×50、M=468×60、L=728×90。
    | 左右レールは同じ 300×250 枠を複数箇所で使う。
    | 公式タグは document.write を使うため、各枠は /ads/arrow/{slot} の中で読む。
    */
    'arrow' => [
        'enabled' => (bool) env('ARROW_ENABLED', false),
        'spots' => [
            'banner_l' => env('ARROW_SPOT_BANNER_L', ''),
            'banner_m' => env('ARROW_SPOT_BANNER_M', ''),
            'banner_s' => env('ARROW_SPOT_BANNER_S', ''),
            'rail' => env('ARROW_SPOT_RAIL', ''),
        ],
    ],
];
