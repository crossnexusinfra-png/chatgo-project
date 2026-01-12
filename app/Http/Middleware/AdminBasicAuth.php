<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminBasicAuth
{
    /**
     * 共有ユーザー名/パスワードでBasic認証を行う。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $expectedUser = (string) config('admin.user');
        $expectedPass = (string) config('admin.password');

        $providedUser = $request->getUser();
        $providedPass = $request->getPassword();

        $valid = hash_equals($expectedUser, (string) $providedUser)
            && hash_equals($expectedPass, (string) $providedPass);

        if (!$valid) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Admin Area"',
            ]);
        }

        // 管理者認証成功を記録
        try {
            \App\Models\AccessLog::create([
                'type' => 'admin_login',
                'user_id' => null,
                'path' => $request->path(),
                'ip' => $request->ip(),
            ]);
        } catch (\Throwable $e) {
            // ログ保存失敗は無視
        }

        return $next($request);
    }
}


