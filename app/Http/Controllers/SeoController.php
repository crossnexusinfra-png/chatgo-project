<?php

namespace App\Http\Controllers;

use App\Services\SeoService;

class SeoController extends Controller
{
    public function robots(SeoService $seo)
    {
        return response($seo->robotsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function adsTxt(SeoService $seo)
    {
        return response($seo->adsTxt(), 200, [
            'Content-Type' => 'text/plain; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function sitemap(SeoService $seo)
    {
        return response($seo->sitemapXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }

    public function exoclickSiteVerification()
    {
        $path = public_path('d0ea5f55b8d3e877c6e03a2fd42efc09.html');
        abort_unless(is_file($path), 404);

        return response((string) file_get_contents($path), 200, [
            'Content-Type' => 'text/html; charset=UTF-8',
            'Cache-Control' => 'public, max-age=300',
        ]);
    }
}
