<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Services\ObservabilityLogService;

class AdminVisitLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        try {
            ObservabilityLogService::recordEvent([
                'event_id' => ObservabilityLogService::eventId($request),
                'request_id' => ObservabilityLogService::requestId($request),
                'event_type' => 'admin_visit',
                'source' => 'server',
                'user_id' => auth()->id(),
                'path' => $request->path(),
                'ip' => $request->ip(),
                'payload' => ['method' => $request->method()],
            ]);
        } catch (\Throwable $e) {
            // 失敗は無視
        }

        return $next($request);
    }
}


