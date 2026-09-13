<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    /**
     * UI の {locale} があればそれを正とし、機械エンドポイントでは URL 生成用のデフォルトだけ置く。
     */
    public function handle(Request $request, Closure $next): Response
    {
        LanguageService::clearRequestLocale();

        $locale = $request->route('locale');
        if (is_string($locale) && LanguageService::isSupported($locale)) {
            LanguageService::applyRequestLocale($locale);

            return $next($request);
        }

        LanguageService::applyUrlDefaults();

        return $next($request);
    }
}
