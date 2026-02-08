<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\CloudflareLogService;

class CloudflareLogMiddleware
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // 本番環境でのみ実行
        if (!CloudflareLogService::isEnabled()) {
            return $next($request);
        }

        $startTime = microtime(true);

        $response = $next($request);

        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000; // ミリ秒

        // アクセスログを保存
        CloudflareLogService::saveAccessLog([
            'method' => $request->method(),
            'url' => $request->fullUrl(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status_code' => $response->getStatusCode(),
            'duration_ms' => round($duration, 2),
            'user_id' => auth()->id(),
        ]);

        // エラーレスポンスの場合は異常検出
        if ($response->getStatusCode() >= 500) {
            CloudflareLogService::detectAnomaly('Server error detected', [
                'status_code' => $response->getStatusCode(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }
}
