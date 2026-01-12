<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

echo "=== Redis設定確認 ===\n\n";

// 1. Redisサーバーの確認
echo "1. Redisサーバーの確認:\n";
$redisAvailable = false;
try {
    $redis = new \Redis();
    $redis->connect('127.0.0.1', 6379);
    $ping = $redis->ping();
    if ($ping === '+PONG' || $ping === 'PONG' || $ping === true) {
        echo "   ✓ Redisサーバーに接続できました\n";
        $redisAvailable = true;
    } else {
        echo "   ✗ Redisサーバーに接続できませんでした\n";
    }
} catch (Exception $e) {
    echo "   ✗ Redisサーバーに接続できませんでした: " . $e->getMessage() . "\n";
}

// 2. PHP Redis拡張の確認
echo "\n2. PHP Redis拡張の確認:\n";
if (extension_loaded('redis')) {
    echo "   ✓ PHP Redis拡張がインストールされています\n";
} else {
    echo "   ✗ PHP Redis拡張がインストールされていません\n";
}

// 3. キャッシュドライバーの確認
echo "\n3. キャッシュドライバーの確認:\n";
$cacheDriver = config('cache.default');
echo "   現在の設定: " . $cacheDriver . "\n";
if ($cacheDriver === 'redis') {
    echo "   ✓ キャッシュはRedisを使用しています\n";
} else {
    echo "   ✗ キャッシュはRedisを使用していません（現在: {$cacheDriver}）\n";
}

// 4. セッションドライバーの確認
echo "\n4. セッションドライバーの確認:\n";
$sessionDriver = config('session.driver');
echo "   現在の設定: " . $sessionDriver . "\n";
if ($sessionDriver === 'redis') {
    echo "   ✓ セッションはRedisを使用しています\n";
} else {
    echo "   ✗ セッションはRedisを使用していません（現在: {$sessionDriver}）\n";
}

// 5. キャッシュの動作テスト
echo "\n5. キャッシュの動作テスト:\n";
try {
    $testKey = 'redis_test_' . time();
    $testValue = 'test_value_' . rand(1000, 9999);
    
    Cache::put($testKey, $testValue, 60);
    $retrieved = Cache::get($testKey);
    
    if ($retrieved === $testValue) {
        echo "   ✓ キャッシュの保存と取得が正常に動作しています\n";
        echo "     保存値: {$testValue}\n";
        echo "     取得値: {$retrieved}\n";
        
        // クリーンアップ
        Cache::forget($testKey);
    } else {
        echo "   ✗ キャッシュの動作に問題があります\n";
        echo "     保存値: {$testValue}\n";
        echo "     取得値: " . ($retrieved ?? 'null') . "\n";
    }
} catch (Exception $e) {
    echo "   ✗ キャッシュの動作テストでエラーが発生しました: " . $e->getMessage() . "\n";
}

// 6. Redis接続情報の確認
echo "\n6. Redis接続情報:\n";
$redisHost = config('database.redis.default.host');
$redisPort = config('database.redis.default.port');
$redisDb = config('database.redis.default.database');
echo "   Host: {$redisHost}\n";
echo "   Port: {$redisPort}\n";
echo "   Database: {$redisDb}\n";

// 7. Redisに保存されているキーの確認（サンプル）
if ($redisAvailable) {
    echo "\n7. Redisに保存されているキーの確認（サンプル）:\n";
    try {
        $keys = $redis->keys('*');
        $sampleKeys = array_slice($keys, 0, 10);
        if (count($sampleKeys) > 0) {
            echo "   見つかったキー（最大10件）:\n";
            foreach ($sampleKeys as $key) {
                echo "     - {$key}\n";
            }
            if (count($keys) > 10) {
                echo "     ... 他 " . (count($keys) - 10) . " 件\n";
            }
        } else {
            echo "   キーが見つかりませんでした（正常です）\n";
        }
    } catch (Exception $e) {
        echo "   キーの取得でエラーが発生しました: " . $e->getMessage() . "\n";
    }
}

echo "\n=== 確認完了 ===\n";

