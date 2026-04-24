<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ObservabilityLogService;

class AccessLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $adminPrefix = trim((string) config('admin.prefix'), '/');
        $isAdminPath = $adminPrefix && str_starts_with(trim($request->path(), '/'), $adminPrefix);

        if (!$isAdminPath) {
            try {
                ObservabilityLogService::recordEvent([
                    'event_id' => ObservabilityLogService::eventId($request),
                    'request_id' => ObservabilityLogService::requestId($request),
                    'event_type' => 'http_request_handled',
                    'source' => 'server',
                    'user_id' => auth()->id(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                    'payload' => [
                        'method' => $request->method(),
                        'status_code' => $response->getStatusCode(),
                    ],
                ]);

                ObservabilityLogService::recordAccess([
                    'request_id' => ObservabilityLogService::requestId($request),
                    'event_id' => ObservabilityLogService::eventId($request),
                    'type' => auth()->check() ? 'member_visit' : 'guest_visit',
                    'method' => $request->method(),
                    'status_code' => $response->getStatusCode(),
                    'source' => 'server',
                    'user_id' => auth()->id(),
                    'path' => $request->path(),
                    'ip' => $request->ip(),
                ]);
            } catch (\Throwable $e) {
                // ログ保存失敗は本処理へ影響させない
            }
        }

        return $response;
    }
}


