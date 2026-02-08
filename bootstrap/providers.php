<?php

$providers = [
    App\Providers\AppServiceProvider::class,
];

// Telescopeがインストールされていて、開発環境の場合のみ追加
// 環境変数を直接チェック（.envファイルが読み込まれる前に実行される可能性があるため）
try {
    $env = 'production'; // デフォルト値
    
    // .envファイルを直接読み込む（Laravelの初期化前でも動作する）
    $envPath = __DIR__ . '/../.env';
    if (file_exists($envPath) && is_readable($envPath)) {
        $envFile = @file_get_contents($envPath);
        if ($envFile !== false && preg_match('/^APP_ENV\s*=\s*(.+)$/m', $envFile, $matches)) {
            $env = trim($matches[1], " \t\n\r\0\x0B\"'");
        }
    }
    
    // 環境変数からも取得を試みる
    if (empty($env) || $env === 'production') {
        $env = getenv('APP_ENV') ?: ($_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? 'production');
    }
    
    // Telescopeがインストールされていて、開発環境の場合のみ追加
    if (class_exists(\Laravel\Telescope\Telescope::class) && 
        ($env === 'local' || $env === 'development')) {
        $providers[] = App\Providers\TelescopeServiceProvider::class;
    }
} catch (\Throwable $e) {
    // エラーが発生した場合はTelescopeを追加しない（安全のため）
    error_log('Error in bootstrap/providers.php: ' . $e->getMessage());
}

return $providers;
