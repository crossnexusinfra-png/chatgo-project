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

    public function sitemap(SeoService $seo)
    {
        return response($seo->sitemapXml(), 200, [
            'Content-Type' => 'application/xml; charset=UTF-8',
            'Cache-Control' => 'public, max-age=3600',
        ]);
    }
}
