<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\AccessLog;

class AccessLogger
{
    public function handle(Request $request, Closure $next): Response
    {
        // 未ログインかつGETのアクセスのみ簡易記録
        if (!auth()->check() && $request->isMethod('GET')) {
            // 管理者エリア自身は除外（任意）
            $adminPrefix = trim((string) config('admin.prefix'), '/');
            if (!$adminPrefix || !str_starts_with(trim($request->path(), '/'), $adminPrefix)) {
                try {
                    AccessLog::create([
                        'type' => 'guest_visit',
                        'user_id' => null,
                        'path' => $request->path(),
                        'ip' => $request->ip(),
                    ]);
                } catch (\Throwable $e) {
                    // ログ保存失敗は無視
                }
            }
        }

        return $next($request);
    }
}


