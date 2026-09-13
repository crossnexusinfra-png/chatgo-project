<?php

namespace Tests\Feature;

use Tests\TestCase;

class LocaleRoutingTest extends TestCase
{
    public function test_ja_and_en_ui_share_the_same_named_routes(): void
    {
        $this->assertSame('/ja', parse_url(route('threads.index', ['locale' => 'ja'], false), PHP_URL_PATH));
        $this->assertSame('/en', parse_url(route('threads.index', ['locale' => 'en'], false), PHP_URL_PATH));
        $this->assertSame('/ja/profile', parse_url(route('profile.index', ['locale' => 'ja'], false), PHP_URL_PATH));
        $this->assertSame('/en/profile', parse_url(route('profile.index', ['locale' => 'en'], false), PHP_URL_PATH));
        $this->assertSame('/ja/threads/1', parse_url(route('threads.show', ['locale' => 'ja', 'thread' => 1], false), PHP_URL_PATH));
        $this->assertSame('/en/threads/1', parse_url(route('threads.show', ['locale' => 'en', 'thread' => 1], false), PHP_URL_PATH));
    }

    public function test_unprefixed_ui_path_redirects_to_localized_url(): void
    {
        $this->get('/profile')->assertRedirect();
        $location = (string) $this->get('/profile')->headers->get('Location');
        $this->assertMatchesRegularExpression('#/(ja|en)/profile$#', parse_url($location, PHP_URL_PATH) ?: $location);

        $this->get('/threads/1')->assertRedirect();
        $threadLocation = (string) $this->get('/threads/1')->headers->get('Location');
        $this->assertMatchesRegularExpression('#/(ja|en)/threads/1$#', parse_url($threadLocation, PHP_URL_PATH) ?: $threadLocation);
    }

    public function test_machine_endpoints_stay_unprefixed(): void
    {
        $this->assertSame('/api/search/more', parse_url(route('api.threads.search.more', absolute: false), PHP_URL_PATH));
        $this->assertSame('/threads/1/responses', parse_url(route('api.threads.responses', ['thread' => 1], false), PHP_URL_PATH));
        $this->assertSame('/auth/google/callback', parse_url(route('auth.provider.callback', ['provider' => 'google'], false), PHP_URL_PATH));
        $this->assertSame('/robots.txt', parse_url(route('seo.robots', absolute: false), PHP_URL_PATH));
        $this->assertSame('/sitemap.xml', parse_url(route('seo.sitemap', absolute: false), PHP_URL_PATH));
    }

    public function test_url_locale_selects_language_without_controller_branch(): void
    {
        $ja = $this->get('/ja/login');
        $en = $this->get('/en/login');

        $ja->assertOk();
        $en->assertOk();
        $ja->assertSee('<html lang="ja"', false);
        $en->assertSee('<html lang="en"', false);
    }

    public function test_unsupported_locale_prefix_is_not_found(): void
    {
        $this->get('/fr/profile')->assertNotFound();
    }
}
