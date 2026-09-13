<?php

namespace App\Services;

use App\Models\Thread;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Route;

class SeoService
{
    /**
     * R18 タグ（Thread::scopeFilterR18Threads と同じ定義）
     *
     * @var list<string>
     */
    private const R18_TAGS = [
        '成人向けメディア・コンテンツ・創作',
        '性体験談・性的嗜好・フェティシズム',
        'アダルト業界・風俗・ナイトワーク',
    ];

    /**
     * robots メタの content 値を返す。
     */
    public function robotsMeta(?Thread $thread = null, ?bool $isR18Thread = null): string
    {
        $routeName = request()->route()?->getName();

        if ($this->isAdultThread($thread, $isR18Thread)) {
            return 'noindex, follow';
        }

        if ($routeName === 'threads.tag') {
            $tag = (string) (request()->route('tag') ?? '');
            if ($this->isR18Tag($tag) || filled(request()->query('q'))) {
                return 'noindex, follow';
            }
        }

        if ($routeName !== null && $this->routeMatches(config('seo.noindex_follow_routes', []), $routeName)) {
            return 'noindex, follow';
        }

        if ($routeName !== null && $this->routeMatches(config('seo.indexable_routes', []), $routeName)) {
            return 'index, follow';
        }

        return 'noindex, nofollow';
    }

    /**
     * クエリパラメータを除いた正規 URL（ホストは APP_URL に揃える）。
     */
    public function canonicalUrl(): string
    {
        $request = request();
        $route = $request->route();
        $routeName = $route?->getName();

        if ($routeName && Route::has($routeName)) {
            try {
                $relative = route(
                    $routeName,
                    $this->canonicalRouteParameters($route->parameters()),
                    false
                );

                return $this->absoluteUrl($this->pathFromGeneratedUrl($relative));
            } catch (\Throwable $e) {
                Log::debug('SeoService canonical route failed', [
                    'route' => $routeName,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->absoluteUrl('/'.ltrim($request->path(), '/'));
    }

    /**
     * @return list<array{hreflang: string, href: string}>
     */
    public function hreflangLinks(): array
    {
        $request = request();
        $route = $request->route();
        $routeName = $route?->getName();

        if ($routeName === null || ! Route::has($routeName)) {
            return [];
        }
        if (! $this->routeMatches(config('seo.indexable_routes', []), $routeName)) {
            return [];
        }

        $params = $this->canonicalRouteParameters($route->parameters());
        unset($params['locale']);

        $links = [];
        foreach (LanguageService::supportedLocales() as $locale) {
            try {
                $relative = route($routeName, $params + ['locale' => $locale], false);
                $links[] = [
                    'hreflang' => $locale,
                    'href' => $this->absoluteUrl($this->pathFromGeneratedUrl($relative)),
                ];
            } catch (\Throwable $e) {
                Log::debug('SeoService hreflang route failed', [
                    'route' => $routeName,
                    'locale' => $locale,
                    'error' => $e->getMessage(),
                ]);
            }
        }

        if ($links === []) {
            return [];
        }

        $default = LanguageService::fallbackLocale();
        $defaultHref = null;
        foreach ($links as $link) {
            if ($link['hreflang'] === $default) {
                $defaultHref = $link['href'];
                break;
            }
        }
        if ($defaultHref !== null) {
            $links[] = [
                'hreflang' => 'x-default',
                'href' => $defaultHref,
            ];
        }

        return $links;
    }

    private function pathFromGeneratedUrl(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);
        if (! is_string($path) || $path === '') {
            $path = str_starts_with($url, '/') ? $url : '/'.$url;
        }

        return $path === '' ? '/' : $path;
    }

    /**
     * robots.txt 本文。
     */
    public function robotsTxt(): string
    {
        $sitemap = $this->absoluteUrl('/sitemap.xml');

        $lines = [
            '# 検索に出したくないページは HTML の noindex で制御する。',
            '# robots.txt で Disallow すると Google が noindex を読めないため、HTML ページは許可する。',
            'User-agent: Mediapartners-Google',
            'Allow: /',
            '',
            'User-agent: Google-Display-Ads-Bot',
            'Allow: /',
            '',
            'User-agent: *',
            'Allow: /',
            'Disallow: /api/',
            '',
            'Sitemap: '.$sitemap,
            '',
        ];

        return implode("\n", $lines);
    }

    /**
     * sitemap.xml 本文。
     */
    public function sitemapXml(): string
    {
        $ttl = (int) config('seo.sitemap_cache_seconds', 3600);
        $cacheKey = 'seo.sitemap.xml.'.md5($this->absoluteUrl('/'));

        if ($ttl > 0) {
            return Cache::remember($cacheKey, $ttl, fn () => $this->buildSitemapXml());
        }

        return $this->buildSitemapXml();
    }

    private function buildSitemapXml(): string
    {
        $urls = array_merge(
            $this->staticSitemapUrls(),
            $this->articleSitemapUrls(),
            $this->categorySitemapUrls(),
            $this->tagSitemapUrls(),
            $this->threadSitemapUrls(),
        );

        $body = [];
        foreach ($urls as $url) {
            $loc = $this->xmlEscape($url['loc']);
            $entry = "  <url>\n    <loc>{$loc}</loc>";
            if (! empty($url['lastmod'])) {
                $entry .= "\n    <lastmod>".$this->xmlEscape($url['lastmod']).'</lastmod>';
            }
            if (! empty($url['changefreq'])) {
                $entry .= "\n    <changefreq>".$this->xmlEscape($url['changefreq']).'</changefreq>';
            }
            if (isset($url['priority'])) {
                $entry .= "\n    <priority>".$this->xmlEscape($url['priority']).'</priority>';
            }
            $entry .= "\n  </url>";
            $body[] = $entry;
        }

        return '<?xml version="1.0" encoding="UTF-8"?>'."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n"
            .implode("\n", $body)."\n"
            .'</urlset>'."\n";
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function staticSitemapUrls(): array
    {
        return array_merge(
            $this->localizedUrlEntries('threads.index', [], 'daily', '1.0'),
            $this->localizedUrlEntries('legal.guide', [], 'monthly', '0.8'),
            $this->localizedUrlEntries('legal.faq', [], 'monthly', '0.8'),
            $this->localizedUrlEntries('legal.articles', [], 'weekly', '0.8'),
            $this->localizedUrlEntries('legal.terms', [], 'monthly', '0.7'),
            $this->localizedUrlEntries('legal.privacy', [], 'monthly', '0.7'),
            $this->localizedUrlEntries('legal.company', [], 'monthly', '0.6'),
            $this->localizedUrlEntries('legal.contact', [], 'monthly', '0.6'),
        );
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function articleSitemapUrls(): array
    {
        $urls = [];
        foreach (ArticleService::all('ja') as $article) {
            $urls = array_merge($urls, $this->localizedUrlEntries(
                'legal.articles.show',
                ['slug' => $article['slug']],
                'monthly',
                '0.7',
                $article['published_at'] ?: null
            ));
        }

        return $urls;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function categorySitemapUrls(): array
    {
        $urls = [];
        foreach (config('seo.sitemap_categories', []) as $category) {
            $urls = array_merge($urls, $this->localizedUrlEntries(
                'threads.category',
                ['category' => $category],
                'daily',
                '0.8'
            ));
        }

        return $urls;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function tagSitemapUrls(): array
    {
        try {
            $validTags = LanguageService::getValidTags();
            $usedTags = Thread::query()
                ->filterR18Threads(false)
                ->whereNotNull('tag')
                ->where('tag', '!=', '')
                ->distinct()
                ->pluck('tag')
                ->all();
        } catch (\Throwable $e) {
            Log::warning('SeoService tag sitemap failed', ['error' => $e->getMessage()]);

            return [];
        }

        $urls = [];
        foreach ($usedTags as $tag) {
            $tag = (string) $tag;
            if ($tag === '' || $this->isR18Tag($tag) || ! in_array($tag, $validTags, true)) {
                continue;
            }
            $urls = array_merge($urls, $this->localizedUrlEntries('threads.tag', ['tag' => $tag], 'weekly', '0.6'));
        }

        return $urls;
    }

    /**
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function threadSitemapUrls(): array
    {
        $localeCount = max(count(LanguageService::supportedLocales()), 1);
        $limit = (int) floor(((int) config('seo.sitemap_thread_limit', 40000)) / $localeCount);
        $urls = [];

        try {
            $count = 0;
            Thread::query()
                ->filterR18Threads(false)
                ->select(['thread_id', 'updated_at'])
                ->chunkById(500, function ($threads) use (&$urls, &$count, $limit) {
                    foreach ($threads as $thread) {
                        if ($count >= $limit) {
                            return false;
                        }
                        $urls = array_merge($urls, $this->localizedUrlEntries(
                            'threads.show',
                            ['thread' => $thread->thread_id],
                            'weekly',
                            '0.6',
                            optional($thread->updated_at)?->toAtomString()
                        ));
                        $count++;
                    }

                    return $count < $limit;
                }, 'thread_id');
        } catch (\Throwable $e) {
            Log::warning('SeoService thread sitemap failed', ['error' => $e->getMessage()]);
        }

        return $urls;
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return array<string, mixed>
     */
    private function canonicalRouteParameters(array $parameters): array
    {
        $filtered = [];
        foreach ($parameters as $key => $value) {
            if ($value instanceof Thread) {
                $filtered[$key] = $value->getKey();

                continue;
            }
            if ($value instanceof User) {
                $filtered[$key] = $value->getKey();

                continue;
            }
            if (is_object($value) && method_exists($value, 'getKey')) {
                $filtered[$key] = $value->getKey();

                continue;
            }
            $filtered[$key] = $value;
        }

        return $filtered;
    }

    /**
     * @param  list<string>  $patterns
     */
    private function routeMatches(array $patterns, string $routeName): bool
    {
        foreach ($patterns as $pattern) {
            if ($pattern === $routeName) {
                return true;
            }
            if (str_ends_with($pattern, '.*') && str_starts_with($routeName, substr($pattern, 0, -1))) {
                return true;
            }
        }

        return false;
    }

    private function isAdultThread(?Thread $thread, ?bool $isR18Thread): bool
    {
        if ($isR18Thread === true) {
            return true;
        }
        if ($thread === null) {
            return false;
        }

        return $thread->is_r18 === true || $this->isR18Tag((string) $thread->tag);
    }

    private function isR18Tag(string $tag): bool
    {
        return in_array($tag, self::R18_TAGS, true);
    }

    /**
     * @param  array<string, mixed>  $parameters
     * @return list<array{loc: string, lastmod?: string, changefreq?: string, priority?: string}>
     */
    private function localizedUrlEntries(string $routeName, array $parameters, string $changefreq, string $priority, ?string $lastmod = null): array
    {
        $urls = [];
        foreach (LanguageService::supportedLocales() as $locale) {
            $urls[] = $this->urlEntry(
                route($routeName, $parameters + ['locale' => $locale]),
                $changefreq,
                $priority,
                $lastmod
            );
        }

        return $urls;
    }

    /**
     * @return array{loc: string, lastmod?: string, changefreq?: string, priority?: string}
     */
    private function urlEntry(string $loc, string $changefreq, string $priority, ?string $lastmod = null): array
    {
        $entry = [
            'loc' => $loc,
            'changefreq' => $changefreq,
            'priority' => $priority,
        ];
        if (is_string($lastmod) && $lastmod !== '') {
            $entry['lastmod'] = $lastmod;
        }

        return $entry;
    }

    private function absoluteUrl(string $path): string
    {
        $base = rtrim((string) config('app.url'), '/');
        if ($path === '/' || $path === '') {
            return $base.'/';
        }

        return $base.'/'.ltrim($path, '/');
    }

    private function xmlEscape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
