<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\View;
use App\Models\AdminMessage;
use App\Models\AdminMessageRead;
use App\Models\CoinSend;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // 必須設定のバリデーション（起動・安全に直結する設定）
        $this->validateRequiredConfigurations();
        
        // キャッシュドライバーの安全性チェック
        $this->ensureSafeCacheDriver();
        
        // パフォーマンス設定の初期化
        $this->initializePerformanceSettings();
        
        // ViewComposerは一時的に無効化（無限ループの問題を回避）
        // 各ビューで直接$langを取得する方式に戻す
        /*
        View::composer('*', function ($view) {
            try {
                $data = $view->getData();
                if (!isset($data['lang'])) {
                    $lang = session('current_language', 'ja');
                    if ($lang === 'ja' && auth()->check()) {
                        $user = auth()->user();
                        $lang = $user->language ?? 'ja';
                    }
                    $view->with('lang', $lang);
                }
            } catch (\Exception $e) {
                $view->with('lang', 'ja');
            }
        });
        */
        
        // ヘッダーに未読お知らせ数を共有（パフォーマンス最適化版）
        View::composer('layouts.header', function ($view) {
            try {
                $unreadCount = 0;
                
                if (auth()->check()) {
                    $userId = auth()->id();
                    
                    // メッセージIDを一括取得（最大50件に制限してパフォーマンス向上）
                    $messageIds = AdminMessage::query()
                        ->whereNotNull('published_at') // 送信済みのみ
                        ->whereNull('parent_message_id') // 返信メッセージは除外
                        ->where(function($q) use ($userId) {
                            $q->where('user_id', $userId) // 個人向け
                              ->orWhere(function($qq) {
                                  $qq->whereNull('user_id')
                                     ->where('audience', 'members'); // 会員向け（個人向けでない）
                              });
                        })
                        ->pluck('id')
                        ->take(50)
                        ->toArray();
                    
                    if (!empty($messageIds)) {
                        // 開封済みメッセージIDを一括取得
                        $readMessageIds = AdminMessageRead::where('user_id', $userId)
                            ->whereIn('admin_message_id', $messageIds)
                            ->pluck('admin_message_id')
                            ->toArray();
                        
                        // 未読数を計算
                        $unreadCount = count($messageIds) - count($readMessageIds);
                    }
                } else {
                    // 非ログインユーザーは非会員向けメッセージ（個人向けでない）を未読としてカウント
                    // パフォーマンス向上のため、最大50件に制限
                    $unreadCount = AdminMessage::whereNotNull('published_at')
                        ->whereNull('parent_message_id') // 返信メッセージは除外
                        ->whereNull('user_id')
                        ->where('audience', 'guests')
                        ->take(50)
                        ->count();
                }
                
                $view->with('unreadNotificationCount', $unreadCount);
            } catch (\Exception $e) {
                // エラーが発生した場合は0を返す（パフォーマンス問題を回避）
                $view->with('unreadNotificationCount', 0);
            }
        });
        
        // フレンドからのコイン受け取り通知をチェック
        View::composer('layouts.app', function ($view) {
            try {
                if (auth()->check()) {
                    $userId = auth()->id();
                    $lastCheckTime = session('last_coin_receive_check', now()->subDays(1));
                    
                    // 最後のチェック以降に受け取ったコイン送信を取得
                    $recentCoinReceives = CoinSend::where('to_user_id', $userId)
                        ->where('sent_at', '>', $lastCheckTime)
                        ->with('fromUser')
                        ->orderBy('sent_at', 'desc')
                        ->get();
                    
                    if ($recentCoinReceives->isNotEmpty()) {
                        // 最新のコイン受け取りを取得
                        $latestReceive = $recentCoinReceives->first();
                        $fromUser = $latestReceive->fromUser;
                        
                        if ($fromUser) {
                            $lang = \App\Services\LanguageService::getCurrentLanguage();
                            $username = $fromUser->username ?? '';
                            $userIdentifier = $fromUser->user_identifier ?? $fromUser->user_id;
                            $displayName = $username . '@' . $userIdentifier;
                            
                            $message = \App\Services\LanguageService::trans('friend_coin_received', $lang, [
                                'name' => $displayName,
                            ]);
                            
                            session()->flash('friend_coin_received_message', $message);
                        }
                    }
                    
                    // チェック時刻を更新
                    session(['last_coin_receive_check' => now()]);
                }
            } catch (\Exception $e) {
                // エラーは無視
            }
        });
    }

    /**
     * 必須設定のバリデーション
     * 起動・安全に直結する設定が正しく設定されているかチェック
     */
    private function validateRequiredConfigurations(): void
    {
        $errors = [];
        
        // 1. APP_KEY（暗号化キー）のチェック
        $appKey = config('app.key');
        if (empty($appKey)) {
            $errors[] = 'APP_KEYが設定されていません。php artisan key:generate を実行してください。';
        } elseif (strlen($appKey) < 32) {
            $errors[] = 'APP_KEYが不正です。32文字以上のランダムな文字列である必要があります。';
        }
        
        // 2. データベース接続設定のチェック（SQLite以外の場合）
        $dbConnection = config('database.default');
        if ($dbConnection !== 'sqlite') {
            $dbConfig = config("database.connections.{$dbConnection}");
            
            if (empty($dbConfig['database'])) {
                $errors[] = "データベース名（DB_DATABASE）が設定されていません。";
            }
            
            if (empty($dbConfig['username'])) {
                $errors[] = "データベースユーザー名（DB_USERNAME）が設定されていません。";
            }
            
            // パスワードは空でも接続できる場合があるため、警告のみ
            if (empty($dbConfig['password'])) {
                Log::warning('データベースパスワード（DB_PASSWORD）が設定されていません。セキュリティ上のリスクがあります。');
            }
        }
        
        // 3. セッション設定のチェック
        $sessionDriver = config('session.driver');
        if (in_array($sessionDriver, ['database', 'redis'])) {
            if ($sessionDriver === 'database') {
                // データベースセッションの場合、テーブルが存在するかはマイグレーションで確認
                // ここでは設定値のみチェック
                if (empty(config('session.table'))) {
                    $errors[] = 'セッションテーブル名（SESSION_TABLE）が設定されていません。';
                }
            } elseif ($sessionDriver === 'redis') {
                // Redisセッションの場合、Redis設定をチェック
                $redisHost = config('database.redis.default.host');
                if (empty($redisHost)) {
                    $errors[] = 'Redisホスト（REDIS_HOST）が設定されていません。';
                }
                // Redisパスワードは空でも接続できる場合があるため、警告のみ
                if (empty(config('database.redis.default.password'))) {
                    Log::warning('Redisパスワード（REDIS_PASSWORD）が設定されていません。セキュリティ上のリスクがあります。');
                }
            }
        }
        
        // 4. 本番環境での追加チェック
        if (app()->environment('production')) {
            if (config('app.debug') === true) {
                $errors[] = '本番環境でAPP_DEBUGがtrueに設定されています。セキュリティ上のリスクがあります。';
            }
            
            if (empty(config('app.url')) || config('app.url') === 'http://localhost') {
                $errors[] = '本番環境でAPP_URLが適切に設定されていません。';
            }
        }
        
        // エラーがある場合は例外をスロー
        if (!empty($errors)) {
            $errorMessage = "必須設定の検証に失敗しました:\n" . implode("\n", $errors);
            Log::error($errorMessage);
            
            // 本番環境では例外をスローして起動を阻止
            if (app()->environment('production')) {
                throw new \RuntimeException($errorMessage);
            } else {
                // 開発環境では警告ログのみ
                Log::warning($errorMessage);
            }
        }
    }

    /**
     * 安全なキャッシュドライバーを確保
     */
    private function ensureSafeCacheDriver(): void
    {
        $driver = config('cache.default');
        
        // Memcachedが設定されているが利用できない場合、fileにフォールバック
        if ($driver === 'memcached' && !extension_loaded('memcached')) {
            config(['cache.default' => 'file']);
        }
        
        // Redisが設定されているが利用できない場合、fileにフォールバック
        if ($driver === 'redis' && !extension_loaded('redis')) {
            config(['cache.default' => 'file']);
        }
    }

    /**
     * パフォーマンス設定の初期化
     */
    private function initializePerformanceSettings(): void
    {
        // キャッシュのTTL設定
        if (!config('cache.ttl')) {
            config(['cache.ttl' => 300]);
        }

        // データベース設定の最適化
        $defaultConnection = config('database.default');
        
        if ($defaultConnection === 'sqlite') {
            $this->optimizeSqliteSettings();
        } elseif ($defaultConnection === 'pgsql') {
            $this->optimizePostgresSettings();
        }
    }

    /**
     * SQLite設定の最適化
     */
    private function optimizeSqliteSettings(): void
    {
        $connection = config('database.connections.sqlite');
        
        if (!isset($connection['journal_mode'])) {
            config(['database.connections.sqlite.journal_mode' => 'WAL']);
        }
        
        if (!isset($connection['synchronous'])) {
            config(['database.connections.sqlite.synchronous' => 'NORMAL']);
        }
        
        if (!isset($connection['cache_size'])) {
            config(['database.connections.sqlite.cache_size' => 10000]);
        }
        
        if (!isset($connection['temp_store'])) {
            config(['database.connections.sqlite.temp_store' => 'MEMORY']);
        }
    }

    /**
     * PostgreSQL設定の最適化
     */
    private function optimizePostgresSettings(): void
    {
        $connection = config('database.connections.pgsql');
        
        // 接続タイムアウトの設定
        if (!isset($connection['options'])) {
            config(['database.connections.pgsql.options' => [
                PDO::ATTR_TIMEOUT => 30,
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            ]]);
        }
        
        // 文字エンコーディングの確認
        if (!isset($connection['charset'])) {
            config(['database.connections.pgsql.charset' => 'utf8']);
        }
    }
}
