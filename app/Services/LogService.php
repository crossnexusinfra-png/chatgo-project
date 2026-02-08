<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;

class LogService
{
    /**
     * 異常ログを記録（エラー以上）
     * 
     * @param string $message ログメッセージ
     * @param array $context コンテキスト情報
     * @param string $level ログレベル（error, critical, alert, emergency）
     * @return void
     */
    public static function logError(string $message, array $context = [], string $level = 'error'): void
    {
        $context['timestamp'] = now()->toDateTimeString();
        
        // HTTPリクエストのコンテキストが利用可能な場合のみ追加
        try {
            if (app()->runningInConsole() === false && request()) {
                $context['ip'] = request()->ip();
                $context['user_agent'] = request()->userAgent();
                $context['url'] = request()->fullUrl();
                $context['method'] = request()->method();
            }
        } catch (\Exception $e) {
            // request()が利用できない場合はスキップ
        }
        
        try {
            if (auth()->check()) {
                $context['user_id'] = auth()->id();
            }
        } catch (\Exception $e) {
            // auth()が利用できない場合はスキップ
        }

        Log::channel('error_file')->{$level}($message, $context);
    }

    /**
     * 警告ログを記録
     * 
     * @param string $message ログメッセージ
     * @param array $context コンテキスト情報
     * @return void
     */
    public static function logWarning(string $message, array $context = []): void
    {
        $context['timestamp'] = now()->toDateTimeString();
        
        // HTTPリクエストのコンテキストが利用可能な場合のみ追加
        try {
            if (app()->runningInConsole() === false && request()) {
                $context['ip'] = request()->ip();
                $context['user_agent'] = request()->userAgent();
                $context['url'] = request()->fullUrl();
                $context['method'] = request()->method();
            }
        } catch (\Exception $e) {
            // request()が利用できない場合はスキップ
        }
        
        try {
            if (auth()->check()) {
                $context['user_id'] = auth()->id();
            }
        } catch (\Exception $e) {
            // auth()が利用できない場合はスキップ
        }

        Log::channel('warning_file')->warning($message, $context);
    }

    /**
     * 例外をログに記録
     * 
     * @param \Throwable $exception 例外
     * @param array $context 追加コンテキスト情報
     * @return void
     */
    public static function logException(\Throwable $exception, array $context = []): void
    {
        $context['exception'] = [
            'message' => $exception->getMessage(),
            'file' => $exception->getFile(),
            'line' => $exception->getLine(),
            'trace' => $exception->getTraceAsString(),
            'code' => $exception->getCode(),
        ];

        self::logError('Exception occurred', $context, 'error');
    }
}
