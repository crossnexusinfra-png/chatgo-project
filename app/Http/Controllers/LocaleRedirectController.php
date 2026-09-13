<?php

namespace App\Http\Controllers;

use App\Services\LanguageService;
use Illuminate\Http\Request;

class LocaleRedirectController extends Controller
{
    /**
     * locale なしのトップを、推定ロケール付きトップへ送る。
     */
    public function home(Request $request)
    {
        return redirect($this->targetPath(LanguageService::preferredUrlLocale(), ''), 302);
    }

    /**
     * 旧 UI パス（/profile など）を /{locale}/profile へ送る。
     */
    public function legacy(Request $request, string $unprefixed)
    {
        $status = $request->isMethod('GET') || $request->isMethod('HEAD') ? 301 : 308;

        return redirect(
            $this->targetPath(LanguageService::preferredUrlLocale(), $unprefixed, $request->getQueryString()),
            $status
        );
    }

    private function targetPath(string $locale, string $unprefixed, ?string $query = null): string
    {
        $path = '/'.$locale;
        $unprefixed = trim($unprefixed, '/');
        if ($unprefixed !== '') {
            $path .= '/'.$unprefixed;
        }
        if (is_string($query) && $query !== '') {
            $path .= '?'.$query;
        }

        return $path;
    }
}
