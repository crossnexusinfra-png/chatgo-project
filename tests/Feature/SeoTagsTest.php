<?php

namespace Tests\Feature;

use App\Models\Thread;
use App\Services\LanguageService;
use App\Services\SeoService;
use Illuminate\Http\Request;
use Tests\TestCase;

class SeoTagsTest extends TestCase
{
    public function test_robots_txt_allows_html_pages_and_disallows_api(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee('User-agent: *', false);
        $response->assertSee('Allow: /', false);
        $response->assertSee('Disallow: /api/', false);
        $response->assertDontSee('Disallow: /login', false);
        $response->assertDontSee('Disallow: /register', false);
        $response->assertDontSee('Disallow: /auth', false);
        $response->assertDontSee('Disallow: /profile', false);
        $response->assertDontSee('Disallow: /notifications', false);
        $response->assertDontSee('Disallow: /friends', false);
        $response->assertSee('User-agent: Mediapartners-Google', false);
        $response->assertSee('Sitemap: ', false);
        $response->assertSee('/sitemap.xml', false);
        $response->assertDontSee('Disallow: /admin', false);
    }

    public function test_sitemap_xml_includes_current_public_pages(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('application/xml', (string) $response->headers->get('Content-Type'));
        $response->assertSee('<urlset', false);
        foreach (LanguageService::supportedLocales() as $locale) {
            $response->assertSee($this->canonicalPath(route('threads.index', ['locale' => $locale], false)), false);
            $response->assertSee($this->canonicalPath(route('legal.terms', ['locale' => $locale], false)), false);
            $response->assertSee($this->canonicalPath(route('legal.privacy', ['locale' => $locale], false)), false);
            $response->assertSee($this->canonicalPath(route('legal.company', ['locale' => $locale], false)), false);
            $response->assertSee($this->canonicalPath(route('legal.contact', ['locale' => $locale], false)), false);
            $response->assertSee($this->canonicalPath(route('legal.guide', ['locale' => $locale], false)), false);
            $response->assertSee($this->canonicalPath(route('legal.faq', ['locale' => $locale], false)), false);
            $response->assertSee($this->canonicalPath(route('legal.articles', ['locale' => $locale], false)), false);
            $response->assertSee($this->canonicalPath(route('legal.articles.show', ['locale' => $locale, 'slug' => 'how-to-connect-worldwide'], false)), false);
            $response->assertSee($this->canonicalPath(route('threads.category', ['locale' => $locale, 'category' => 'popular'], false)), false);
            $response->assertSee($this->canonicalPath(route('threads.category', ['locale' => $locale, 'category' => 'latest'], false)), false);
        }
        $response->assertDontSee('/login</loc>', false);
        $response->assertDontSee('/register</loc>', false);
        $response->assertDontSee('/search', false);
        $response->assertDontSee('/profile</loc>', false);
        $response->assertDontSee('/friends</loc>', false);
        $response->assertDontSee('/notifications', false);
        $response->assertDontSee('/user/', false);
        $response->assertDontSee('/auth', false);
    }

    public function test_legal_pages_are_indexable_with_self_canonical(): void
    {
        $response = $this->get('/en/terms');

        $response->assertOk();
        $response->assertSee('<meta name="robots" content="index, follow">', false);
        $this->assertCanonicalEndsWith($response->getContent(), '/en/terms');
        $response->assertSee('hreflang="ja"', false);
        $response->assertSee('hreflang="en"', false);
        $response->assertSee('hreflang="x-default"', false);
    }

    public function test_standalone_legal_pages_have_logo_link_to_home(): void
    {
        $homeHref = route('threads.index', ['locale' => 'en']);

        foreach (['/en/terms', '/en/privacy', '/en/company', '/en/contact'] as $path) {
            $response = $this->get($path);

            $response->assertOk();
            $response->assertSee('header-logo-only', false);
            $response->assertSee('href="'.$homeHref.'"', false);
            $response->assertDontSee('search-form', false);
            $response->assertDontSee('header-button', false);
        }
    }

    public function test_guide_and_articles_are_indexable(): void
    {
        $guide = $this->get('/en/guide');
        $guide->assertOk();
        $guide->assertSee('<meta name="robots" content="index, follow">', false);
        $this->assertCanonicalEndsWith($guide->getContent(), '/en/guide');

        $articles = $this->get('/en/articles');
        $articles->assertOk();
        $articles->assertSee('<meta name="robots" content="index, follow">', false);
        $this->assertCanonicalEndsWith($articles->getContent(), '/en/articles');
    }

    public function test_login_and_register_are_noindex(): void
    {
        $login = $this->get('/en/login');
        $login->assertOk();
        $login->assertSee('<meta name="robots" content="noindex, nofollow">', false);
        $this->assertCanonicalEndsWith($login->getContent(), '/en/login');

        $this->get('/en/register')->assertOk()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_unknown_page_is_noindex(): void
    {
        $this->get('/this-path-does-not-exist')->assertNotFound()
            ->assertSee('<meta name="robots" content="noindex, nofollow">', false);
    }

    public function test_homepage_and_public_listings_are_indexable(): void
    {
        $this->bindMatchedRequest('/en');
        $this->assertSame('index, follow', app(SeoService::class)->robotsMeta());
        $this->assertCanonicalPath('/en', app(SeoService::class)->canonicalUrl());

        $this->bindMatchedRequest('/en/category/popular?sort_by=latest&period=30');
        $this->assertSame('index, follow', app(SeoService::class)->robotsMeta());
        $this->assertCanonicalPath('/en/category/popular', app(SeoService::class)->canonicalUrl());
    }

    public function test_search_and_private_pages_are_noindex(): void
    {
        $this->bindMatchedRequest('/en/search?q=test');
        $this->assertSame('noindex, follow', app(SeoService::class)->robotsMeta());
        $this->assertCanonicalPath('/en/search', app(SeoService::class)->canonicalUrl());

        $this->bindMatchedRequest('/en/profile');
        $this->assertSame('noindex, nofollow', app(SeoService::class)->robotsMeta());

        $this->bindMatchedRequest('/en/user/1');
        $this->assertSame('noindex, follow', app(SeoService::class)->robotsMeta());

        $this->bindMatchedRequest('/en/friends');
        $this->assertSame('noindex, nofollow', app(SeoService::class)->robotsMeta());
    }

    public function test_r18_threads_and_tag_pages_are_noindex(): void
    {
        $this->bindMatchedRequest('/en/threads/1');
        $thread = new Thread(['is_r18' => true, 'tag' => '雑談']);
        $this->assertSame('noindex, follow', app(SeoService::class)->robotsMeta($thread, true));

        $this->bindMatchedRequest('/en/tag/'.rawurlencode('雑談'));
        $this->assertSame('index, follow', app(SeoService::class)->robotsMeta());

        $this->bindMatchedRequest('/en/tag/'.rawurlencode('性体験談・性的嗜好・フェティシズム'));
        $this->assertSame('noindex, follow', app(SeoService::class)->robotsMeta());
    }

    private function bindMatchedRequest(string $uri): void
    {
        $request = Request::create($uri, 'GET');
        $route = app('router')->getRoutes()->match($request);
        $request->setRouteResolver(static fn () => $route);
        app()->instance('request', $request);
        $locale = $route->parameter('locale');
        if (is_string($locale) && LanguageService::isSupported($locale)) {
            LanguageService::applyRequestLocale($locale);
        }
    }

    private function canonicalPath(string $url): string
    {
        $path = parse_url($url, PHP_URL_PATH);

        return is_string($path) && $path !== '' ? $path : $url;
    }

    private function assertCanonicalEndsWith(string $html, string $path): void
    {
        $this->assertMatchesRegularExpression(
            '#<link rel="canonical" href="[^"]+'.preg_quote($path, '#').'">#',
            $html
        );
    }

    private function assertCanonicalPath(string $expectedPath, string $canonical): void
    {
        $path = parse_url($canonical, PHP_URL_PATH) ?: '/';
        $this->assertSame($expectedPath === '/' ? '/' : $expectedPath, $path === '' ? '/' : $path);
        $this->assertNull(parse_url($canonical, PHP_URL_QUERY));
    }
}
