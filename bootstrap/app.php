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
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // Sentryに例外を送信
        if (app()->bound('sentry')) {
            $exceptions->report(function (\Throwable $e) {
                app('sentry')->captureException($e);
            });
        }
        
        // カスタムエラービューを使用する設定
        // 開発環境でもカスタムエラービューを表示する場合は、以下のコメントを外してください
        // $exceptions->render(function (\Throwable $e, \Illuminate\Http\Request $request) {
        //     return null; // nullを返すとLaravelのデフォルトのエラーハンドリングが使用されます
        // });
    })->create();
