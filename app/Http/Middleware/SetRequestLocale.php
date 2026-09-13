<?php

namespace App\Http\Middleware;

use App\Services\LanguageService;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SetRequestLocale
{
    /**
     * 表示言語は URL の {locale}。リンクの locale は設定／国判定のデフォルト。
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = $request->route('locale');
        if (is_string($locale) && LanguageService::isSupported($locale)) {
            LanguageService::applyRequestLocale($locale);
        } else {
            LanguageService::clearRequestLocale();
        }

        LanguageService::applyUrlDefaults();

        return $next($request);
    }
}
