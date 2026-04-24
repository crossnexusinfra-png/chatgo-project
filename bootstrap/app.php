<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Str;
use App\Services\ObservabilityLogService;

return Application::configure(basePath: dirname(__DIR__))
    ->withProviders([
        \App\Providers\AuthServiceProvider::class,
    ])
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            // 管理者ルートは routes/web.php の先頭で読み込み（優先マッチのため）

            // クライアントIPは TrustCloudflareProxies + TrustProxies により $request->ip() で取得（CF-Connecting-IP を X-Forwarded-For に反映済み）

            // /api（JSONデータ取得）: 60/分 user_id, 100/分 IP
            RateLimiter::for('api', function (Request $request) {
                $ip = $request->ip();
                $uid = $request->user()?->id;
                return [
                    Limit::perMinute(60)->by($uid ? "user:{$uid}" : "ip:{$ip}"),
                    Limit::perMinute(100)->by("ip:{$ip}"),
                ];
            });

            // 検索系（/search 含む）: 20/分 IP, 20/分 user_id
            RateLimiter::for('search', function (Request $request) {
                $ip = $request->ip();
                $uid = $request->user()?->id;
                return [
                    Limit::perMinute(20)->by("ip:{$ip}"),
                    Limit::perMinute(20)->by($uid ? "user:{$uid}" : "ip:{$ip}"),
                ];
            });

            // 初期登録時 認証コード再送信（SMS）: 1/分 IP, 1/分 phone
            RateLimiter::for('verification_initial_sms', function (Request $request) {
                $ip = $request->ip();
                $phone = $request->session()->get('registration_data.phone', '');
                return [
                    Limit::perMinute(1)->by("ip:{$ip}"),
                    Limit::perMinute(1)->by('phone:' . ($phone ?: 'unknown')),
                ];
            });

            // 初期登録時 認証コード再送信（email）: 1/分 IP, 1/分 email
            RateLimiter::for('verification_initial_email', function (Request $request) {
                $ip = $request->ip();
                $email = $request->session()->get('registration_data.email', '');
                return [
                    Limit::perMinute(1)->by("ip:{$ip}"),
                    Limit::perMinute(1)->by('email:' . ($email ?: 'unknown')),
                ];
            });

            // パスワード再設定リンク（メール）: 5/分 IP, 3/分 メールアドレス
            RateLimiter::for('password_reset_email', function (Request $request) {
                $ip = $request->ip();
                $email = strtolower((string) $request->input('email', ''));
                return [
                    Limit::perMinute(5)->by("ip:{$ip}"),
                    Limit::perMinute(3)->by('pwreset_email:' . ($email ?: 'unknown')),
                ];
            });

            // パスワード再設定リンク（SMS・電話番号）: 5/分 IP, 3/分 国番号+番号
            RateLimiter::for('password_reset_phone', function (Request $request) {
                $ip = $request->ip();
                $key = (string) $request->input('phone_country', '') . '|' . (string) $request->input('phone_local', '');
                return [
                    Limit::perMinute(5)->by("ip:{$ip}"),
                    Limit::perMinute(3)->by('pwreset_phone:' . ($key !== '|' ? $key : 'unknown')),
                ];
            });

            // 電話番号・メアド変更時 認証コード再送信: 1/分 user_id
            RateLimiter::for('verification_profile', function (Request $request) {
                $uid = $request->user()?->id;
                return Limit::perMinute(1)->by($uid ? "user:{$uid}" : 'ip:' . $request->ip());
            });

            // 投稿系（ルーム作成・リプライ・返信）: 10/分 user_id, 30/分 IP
            RateLimiter::for('post', function (Request $request) {
                $ip = $request->ip();
                $uid = $request->user()?->id;
                return [
                    Limit::perMinute(10)->by($uid ? "user:{$uid}" : "ip:{$ip}"),
                    Limit::perMinute(30)->by("ip:{$ip}"),
                ];
            });

            // Google Safe Browsing（コントローラ内で手動チェック）: 20/分 user_id
            RateLimiter::for('safebrowsing', function (Request $request) {
                $uid = $request->user()?->id;
                $ip = $request->ip();
                return Limit::perMinute(20)->by($uid ? "user:{$uid}" : "ip:{$ip}");
            });

            // Veriphone（登録・プロフィール電話検証）: 5/分 IP, 5/分 user_id
            RateLimiter::for('veriphone', function (Request $request) {
                $ip = $request->ip();
                $uid = $request->user()?->id;
                return [
                    Limit::perMinute(5)->by("ip:{$ip}"),
                    Limit::perMinute(5)->by($uid ? "user:{$uid}" : "ip:{$ip}"),
                ];
            });

            // OpenAI 翻訳: ライブ翻訳 POST は throttle:api（60/分・ユーザー、100/分・IP）。将来 throttle:openai を付ける場合用に api と同じ二重枠
            RateLimiter::for('openai', function (Request $request) {
                $ip = $request->ip();
                $uid = $request->user()?->id;

                return [
                    Limit::perMinute(60)->by($uid ? "user:{$uid}" : "ip:{$ip}"),
                    Limit::perMinute(100)->by("ip:{$ip}"),
                ];
            });

            // 広告API（今後実装予定）: 5/分 user_id
            RateLimiter::for('ad_api', function (Request $request) {
                $uid = $request->user()?->id;
                return Limit::perMinute(5)->by($uid ? "user:{$uid}" : 'ip:' . $request->ip());
            });

            // コイン送信: 3/分 user_id, 20/日 user_id
            RateLimiter::for('coins_send', function (Request $request) {
                $uid = $request->user()?->id;
                if (!$uid) {
                    return Limit::perMinute(1)->by('ip:' . $request->ip());
                }
                return [
                    Limit::perMinute(3)->by("user:{$uid}"),
                    Limit::perDay(20)->by("user:{$uid}"),
                ];
            });

            // 通報: 10/分 user_id
            RateLimiter::for('reports', function (Request $request) {
                $uid = $request->user()?->id;
                return Limit::perMinute(10)->by($uid ? "user:{$uid}" : 'ip:' . $request->ip());
            });

            // お知らせ返信: 5/分 user_id
            RateLimiter::for('notice_reply', function (Request $request) {
                $uid = $request->user()?->id;
                return Limit::perMinute(5)->by($uid ? "user:{$uid}" : 'ip:' . $request->ip());
            });

            // 改善要望: 3/分 user_id
            RateLimiter::for('suggestions', function (Request $request) {
                $uid = $request->user()?->id;
                $ip = $request->ip();
                return Limit::perMinute(3)->by($uid ? "user:{$uid}" : "ip:{$ip}");
            });

            // 凍結異議申し立て: 3/分 user_id
            RateLimiter::for('freeze_appeals', function (Request $request) {
                $uid = $request->user()?->id;
                $ip = $request->ip();
                return Limit::perMinute(3)->by($uid ? "user:{$uid}" : "ip:{$ip}");
            });

            // ログイン試行: 20 req/min IP, 5 req/min ログイン対象メール（user_id 単位）
            RateLimiter::for('login', function (Request $request) {
                $ip = $request->ip();
                $email = $request->input('email');
                $emailKey = $email !== null && $email !== '' ? 'email:' . strtolower(trim($email)) : 'email:unknown';
                return [
                    Limit::perMinute(20)->by("ip:{$ip}"),
                    Limit::perMinute(5)->by($emailKey),
                ];
            });
        },
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->prepend(\App\Http\Middleware\RequestCorrelationId::class);

        // リプライ・ルーム1リプライ目のコイン計算は先頭末尾の空白・改行も1文字と数える（フロントと一致させる）
        $middleware->trimStrings(except: [
            'body',
        ]);

        $middleware->trustProxies(
            '*',
            Request::HEADER_X_FORWARDED_FOR
            | Request::HEADER_X_FORWARDED_HOST
            | Request::HEADER_X_FORWARDED_PORT
            | Request::HEADER_X_FORWARDED_PROTO
        );
        // CF-Connecting-IP を X-Forwarded-For に反映し、$request->ip() でクライアントIPを取得（TrustProxies と併用）
        $middleware->prepend(\App\Http\Middleware\TrustCloudflareProxies::class);
        // CSPヘッダー追加（nonce対応）
        $middleware->append(\App\Http\Middleware\CspMiddleware::class);
        
        // パフォーマンス監視ミドルウェアを追加
        $middleware->append(\App\Http\Middleware\PerformanceMonitor::class);

        // 凍結チェックは web グループ内（StartSession 後）で実行する。
        // グローバルに置くとセッション前に走り Auth::check() が常に false になり、凍結が無効化される。
        $middleware->web(append: [
            \App\Http\Middleware\CheckUserFrozen::class,
        ]);
        
        // 管理者用Basic認証ミドルウェアのエイリアス
        $middleware->alias([
            'admin.basic' => \App\Http\Middleware\AdminBasicAuth::class,
            'admin.visit' => \App\Http\Middleware\AdminVisitLogger::class,
            // リクエストの user_id / from_user_id が認証ユーザーと一致するか検証（権限制御）
            'request.user' => \App\Http\Middleware\EnsureRequestUserAuthorized::class,
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
                $req = request();
                $requestId = ObservabilityLogService::requestId($req);
                $eventId = ObservabilityLogService::eventId($req);

                if (class_exists(\App\Services\LogService::class)) {
                    \App\Services\LogService::logException($e, [
                        'request_id' => $requestId,
                        'event_id' => $eventId,
                    ]);
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
                        'request_id' => $requestId,
                        'event_id' => $eventId,
                    ]);
                }

                ObservabilityLogService::recordError([
                    'error_id' => (string) Str::uuid(),
                    'request_id' => $requestId,
                    'event_id' => $eventId,
                    'source' => 'server',
                    'status_code' => null,
                    'error_type' => get_class($e),
                    'message' => $e->getMessage(),
                    'path' => $req?->path(),
                    'method' => $req?->method(),
                    'ip' => $req?->ip(),
                    'user_id' => auth()->id(),
                    'context' => [
                        'file' => $e->getFile(),
                        'line' => $e->getLine(),
                    ],
                ]);
                
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
