<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "=== セッション動作テスト ===\n\n";

// セッションの動作テスト
try {
    // セッションを開始
    $session = session();
    
    // テスト値を保存
    $testValue = 'session_test_' . rand(1000, 9999);
    $session->put('test_session_key', $testValue);
    $session->save();
    
    echo "1. セッションへの保存:\n";
    echo "   保存値: {$testValue}\n";
    
    // セッションから取得
    $retrieved = $session->get('test_session_key');
    
    echo "\n2. セッションからの取得:\n";
    echo "   取得値: {$retrieved}\n";
    
    if ($retrieved === $testValue) {
        echo "\n   ✓ セッションが正常に動作しています\n";
    } else {
        echo "\n   ✗ セッションの動作に問題があります\n";
    }
    
    // セッションドライバーの確認
    echo "\n3. セッションドライバー:\n";
    $driver = config('session.driver');
    echo "   ドライバー: {$driver}\n";
    
    if ($driver === 'redis') {
        echo "   ✓ Redisを使用しています\n";
    } else {
        echo "   ⚠ Redisを使用していません（現在: {$driver}）\n";
    }
    
} catch (Exception $e) {
    echo "エラーが発生しました: " . $e->getMessage() . "\n";
    echo "スタックトレース:\n" . $e->getTraceAsString() . "\n";
}

echo "\n=== テスト完了 ===\n";

