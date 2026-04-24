<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\CloudflareLogService;
use App\Services\ObservabilityLogService;

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
            'request_id' => ObservabilityLogService::requestId($request),
            'event_id' => ObservabilityLogService::eventId($request),
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
            ObservabilityLogService::recordError([
                'error_id' => (string) \Illuminate\Support\Str::uuid(),
                'request_id' => ObservabilityLogService::requestId($request),
                'event_id' => ObservabilityLogService::eventId($request),
                'source' => 'infrastructure',
                'status_code' => $response->getStatusCode(),
                'error_type' => 'http_5xx',
                'message' => 'Cloudflare middleware detected 5xx response',
                'path' => $request->path(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_id' => auth()->id(),
                'context' => ['url' => $request->fullUrl()],
            ]);
            CloudflareLogService::detectAnomaly('Server error detected', [
                'status_code' => $response->getStatusCode(),
                'url' => $request->fullUrl(),
            ]);
        }

        return $response;
    }
}
