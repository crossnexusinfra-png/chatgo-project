<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\AuthServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // 管理者専用ルートを別ファイルから読み込む
            require __DIR__.'/../routes/admin.php';
            
            // レート制限の設定
            RateLimiter::for('api', function (Request $request) {
                return Limit::perMinute(60)->by($request->user()?->id ?: $request->ip());
            });
            
            // ログイン試行のレート制限（1分間に5回）
            RateLimiter::for('login', function (Request $request) {
                return Limit::perMinute(5)->by($request->ip());
            });
            
            // 認証コード送信のレート制限（1分間に1回）
            RateLimiter::for('verification', function (Request $request) {
                return Limit::perMinute(1)->by($request->ip());
            });
            
            // 投稿のレート制限（1分間に10回）
            RateLimiter::for('post', function (Request $request) {
                return Limit::perMinute(10)->by($request->user()?->id ?: $request->ip());
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {

        $middleware->trustProxies(
            proxies: '*',
            headers: Request::HEADER_X_FORWARDED_FOR
                | Request::HEADER_X_FORWARDED_HOST
                | Request::HEADER_X_FORWARDED_PORT
                | Request::HEADER_X_FORWARDED_PROTO
        );
        // CSPヘッダー追加（nonce対応）
        $middleware->append(\App\Http\Middleware\CspMiddleware::class);
        
        // パフォーマンス監視ミドルウェアを追加
        $middleware->append(\App\Http\Middleware\PerformanceMonitor::class);
        
        // 凍結チェックミドルウェアを追加
        $middleware->append(\App\Http\Middleware\CheckUserFrozen::class);
        
        // 管理者用Basic認証ミドルウェアのエイリアス
        $middleware->alias([
            'admin.basic' => \App\Http\Middleware\AdminBasicAuth::class,
            'admin.visit' => \App\Http\Middleware\AdminVisitLogger::class,
        ]);

        // ゲストアクセス記録
        $middleware->append(\App\Http\Middleware\AccessLogger::class);
        
        // Cloudflareログ記録（本番環境のみ）
        // 環境変数を直接チェック（app()は依存性注入のコンテキストで問題を起こす可能性があるため）
        $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
        if ($env === 'production') {
            $middleware->append(\App\Http\Middleware\CloudflareLogMiddleware::class);
        }
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentryに例外を送信
        if (app()->bound('sentry')) {
            $exceptions->report(function (\Throwable $e) {
                app('sentry')->captureException($e);
            });
        }
        
        // 異常ログを記録（エラー以上）
        $exceptions->report(function (\Throwable $e) {
            try {
                if (class_exists(\App\Services\LogService::class)) {
                    \App\Services\LogService::logException($e);
                } else {
                    // LogServiceが存在しない場合は直接ログに記録
                    \Log::channel('error_file')->error('Exception occurred', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                        'trace' => $e->getTraceAsString(),
                        'url' => request()->fullUrl(),
                        'method' => request()->method(),
                        'ip' => request()->ip(),
                        'user_id' => auth()->id(),
                    ]);
                }
                
                // Cloudflare異常検出
                // 環境変数を直接チェック（app()は依存性注入のコンテキストで問題を起こす可能性があるため）
                $env = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV') ?: 'production';
                if ($env === 'production' && class_exists(\App\Services\CloudflareLogService::class)) {
                    \App\Services\CloudflareLogService::detectAnomaly('Exception occurred', [
                        'message' => $e->getMessage(),
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ]);
                }
            } catch (\Throwable $logError) {
                // ログ記録自体が失敗した場合はerror_logを使用
                error_log('Failed to log exception: ' . $logError->getMessage());
                error_log('Original exception: ' . $e->getMessage());
            }
        });
        
        // カスタムエラービューを使用する設定
        // 開発環境でもカスタムエラービューを表示する場合は、以下のコメントを外してください
        // $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
        //     return null; // nullを返すとLaravelのデフォルトのエラーハンドリングが使用されます
        // });
    })->create();
