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

    // 本番環境のGoogle AdManager設定
    'ad_manager_publisher_id' => env('AD_MANAGER_PUBLISHER_ID', ''),
    'ad_manager_app_id' => env('AD_MANAGER_APP_ID', ''),
    'ad_manager_ad_unit_id' => env('AD_MANAGER_AD_UNIT_ID', ''),
];

