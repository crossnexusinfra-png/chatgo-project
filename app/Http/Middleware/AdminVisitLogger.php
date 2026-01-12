<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminVisitLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        // 管理者ページ訪問を記録（Basic認証通過後）
        try {
            \App\Models\AccessLog::create([
                'type' => 'admin_visit',
                'user_id' => null,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // 失敗は無視
        }

        return $next($request);
    }
}


