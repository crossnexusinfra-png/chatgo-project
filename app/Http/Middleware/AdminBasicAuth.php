<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Models\Admin;
use Illuminate\Support\Facades\Hash;

class AdminBasicAuth
{
    /**
     * DBに保存された管理者アカウントでBasic認証を行う。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $providedUser = $request->getUser();
        $providedPass = $request->getPassword();

        if (!$providedUser || !$providedPass) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="Admin Area"',
            ]);
        }

        // ユーザー名またはメールアドレスで管理者を検索
        $admin = Admin::where('username', $providedUser)
            ->orWhere('email', $providedUser)
            ->first();

        // 管理者が見つからない、またはパスワードが一致しない場合
        if (!$admin || !Hash::check($providedPass, $admin->password)) {
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


