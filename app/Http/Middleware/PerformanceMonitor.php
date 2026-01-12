<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class PerformanceMonitor
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure(\Illuminate\Http\Request): (\Illuminate\Http\Response|\Illuminate\Http\RedirectResponse)  $next
     * @return \Illuminate\Http\Response|\Illuminate\Http\RedirectResponse
     */
    public function handle(Request $request, Closure $next)
    {
        try {
            $startTime = microtime(true);
            $startMemory = memory_get_usage();

            $response = $next($request);

            $endTime = microtime(true);
            $endMemory = memory_get_usage();
            
            $executionTime = ($endTime - $startTime) * 1000; // ミリ秒
            $memoryUsage = $endMemory - $startMemory;

            // レスポンスが遅い場合（500ms以上）はログに記録
            if ($executionTime > 500) {
                Log::warning('Slow response detected', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'execution_time_ms' => round($executionTime, 2),
                    'memory_usage_bytes' => $memoryUsage,
                    'user_agent' => $request->userAgent(),
                    'ip' => $request->ip(),
                ]);
            }

            // レスポンスヘッダーにパフォーマンス情報を追加（開発環境のみ）
            if (app()->environment('local', 'development')) {
                $response->headers->set('X-Execution-Time', round($executionTime, 2) . 'ms');
                $response->headers->set('X-Memory-Usage', number_format($memoryUsage) . ' bytes');
            }

            return $response;
        } catch (\Exception $e) {
            // エラーが発生した場合はログに記録してから再スロー
            Log::error('Performance monitoring error', [
                'error' => $e->getMessage(),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
            ]);
            
            // エラーが発生してもアプリケーションは継続動作
            return $next($request);
        }
    }
}
