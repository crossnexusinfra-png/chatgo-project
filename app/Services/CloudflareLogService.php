<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use App\Services\SecureHttpClientService;

class CloudflareLogService
{
    /**
     * Cloudflareが有効かどうかを確認
     * 
     * @return bool
     */
    public static function isEnabled(): bool
    {
        return config('cloudflare.enabled') && 
               app()->environment('production') &&
               !empty(config('cloudflare.api_token')) &&
               !empty(config('cloudflare.zone_id'));
    }

    /**
     * アクセスログを保存
     * 
     * @param array $logData ログデータ
     * @return void
     */
    public static function saveAccessLog(array $logData): void
    {
        if (!self::isEnabled()) {
            return;
        }

        try {
            // Cloudflare APIを使用してログを保存
            // 実際の実装は本番環境でCloudflareの設定を確認してから行う
            // ここではファイルログに記録する実装例
            
            $logPath = storage_path('logs/cloudflare-access.log');
            $logMessage = json_encode([
                'timestamp' => now()->toDateTimeString(),
                ...$logData,
            ], JSON_UNESCAPED_UNICODE) . PHP_EOL;

            file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
        } catch (\Exception $e) {
            Log::channel('error_file')->error('Cloudflare log save failed', [
                'error' => $e->getMessage(),
                'log_data' => $logData,
            ]);
        }
    }

    /**
     * 異常を検出して通知
     * 
     * @param string $message メッセージ
     * @param array $context コンテキスト
     * @return void
     */
    public static function detectAnomaly(string $message, array $context = []): void
    {
        if (!self::isEnabled() || !config('cloudflare.alerts.enabled')) {
            return;
        }

        try {
            // エラーカウントを増加
            $cacheKey = 'cloudflare_error_count_' . now()->format('Y-m-d-H-i');
            $errorCount = Cache::increment($cacheKey, 1);
            Cache::put($cacheKey, $errorCount, now()->addMinutes(5));

            // 閾値を超えた場合に通知
            if ($errorCount >= config('cloudflare.analysis.error_threshold')) {
                self::sendAlert($message, $context);
            }
        } catch (\Exception $e) {
            Log::channel('error_file')->error('Cloudflare anomaly detection failed', [
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * アラートを送信
     * 
     * @param string $message メッセージ
     * @param array $context コンテキスト
     * @return void
     */
    protected static function sendAlert(string $message, array $context = []): void
    {
        $alertData = [
            'message' => $message,
            'context' => $context,
            'timestamp' => now()->toDateTimeString(),
            'environment' => app()->environment(),
        ];

        // Webhook URLが設定されている場合
        if ($webhookUrl = config('cloudflare.alerts.webhook_url')) {
            try {
                // セキュアなHTTPクライアントを使用
                SecureHttpClientService::post($webhookUrl, $alertData);
            } catch (\Exception $e) {
                Log::channel('error_file')->error('Cloudflare webhook alert failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        // メールが設定されている場合
        if ($email = config('cloudflare.alerts.email')) {
            try {
                \Mail::raw(json_encode($alertData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), function ($mail) use ($email, $message) {
                    $mail->to($email)
                         ->subject('Cloudflare Alert: ' . $message);
                });
            } catch (\Exception $e) {
                Log::channel('error_file')->error('Cloudflare email alert failed', [
                    'error' => $e->getMessage(),
                ]);
            }
        }
    }
}
