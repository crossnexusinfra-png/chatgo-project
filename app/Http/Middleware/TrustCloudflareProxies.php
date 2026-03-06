<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Cloudflare 経由時、CF-Connecting-IP を X-Forwarded-For に反映する。
 * これにより Laravel 標準の TrustProxies + $request->ip() でクライアントIPを取得できる。
 * （プロキシのIPを誤って参照しないようにするため、先頭で prepend して実行する）
 */
class TrustCloudflareProxies
{
    public function handle(Request $request, Closure $next): Response
    {
        $connectingIp = $request->header('CF-Connecting-IP');
        if ($connectingIp !== null && $connectingIp !== '') {
            $request->server->set('HTTP_X_FORWARDED_FOR', $connectingIp);
        }

        return $next($request);
    }
}
