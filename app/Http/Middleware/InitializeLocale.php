<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Symfony\Component\HttpFoundation\Response;

class InitializeLocale
{
    /**
     * ワーカー再利用でも前リクエストの locale を残さない。セッションはまだ使わない。
     */
    public function handle(Request $request, Closure $next): Response
    {
        LanguageService::clearRequestLocale();
        URL::defaults(['locale' => LanguageService::fallbackLocale()]);

        $first = $request->segment(1);
        if (is_string($first) && LanguageService::isSupported($first)) {
            LanguageService::applyRequestLocale($first, false);
        }

        return $next($request);
    }
}
