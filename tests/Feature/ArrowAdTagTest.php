<?php

namespace Tests\Feature;

use Tests\TestCase;

class ArrowAdTagTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ads.arrow.enabled' => true,
            'ads.arrow.spots.banner_l' => '14110',
            'ads.arrow.spots.banner_m' => '14109',
            'ads.arrow.spots.banner_s' => '14108',
            'ads.arrow.spots.rail' => '14111',
        ]);
    }

    public function test_arrow_document_contains_the_official_tag_for_one_spot(): void
    {
        $response = $this->get('/ads/arrow/s');

        $response->assertOk();
        $response->assertSee('ARROW_adspotId = 14108;', false);
        $response->assertSee('ARROW_ifw = "320px";', false);
        $response->assertSee('ARROW_ifh = "50px";', false);
        $response->assertSee('https://ad.ad-arrow.com/static/banner.js', false);
        $response->assertDontSee('ARROW_adspotId = 14109;', false);
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString("frame-ancestors 'self'", $csp);
        $this->assertStringContainsString('https://ad.ad-arrow.com', $csp);
    }

    public function test_unknown_arrow_slot_is_not_found(): void
    {
        $this->get('/ads/arrow/native')->assertNotFound();
    }

    public function test_r18_banner_lists_size_urls_and_rails_share_one_spot(): void
    {
        $banner = view('components.adsense-inline-banner', [
            'instanceId' => 'r18-banner',
            'network' => 'arrow',
            'isR18Thread' => true,
        ])->render();

        $this->assertStringContainsString('data-ad-network="arrow"', $banner);
        $this->assertStringContainsString('data-arrow-src-s="'.route('ads.arrow', ['slot' => 's']).'"', $banner);
        $this->assertStringContainsString('data-arrow-src-m="'.route('ads.arrow', ['slot' => 'm']).'"', $banner);
        $this->assertStringContainsString('data-arrow-src-l="'.route('ads.arrow', ['slot' => 'l']).'"', $banner);
        $this->assertStringNotContainsString('data-zoneid', $banner);
        $this->assertStringNotContainsString('data-gpt-slot', $banner);

        $left = view('components.adsense-rail-unit', [
            'side' => 'left',
            'instanceId' => 'rail-left',
            'network' => 'arrow',
            'isR18Thread' => true,
        ])->render();
        $right = view('components.adsense-rail-unit', [
            'side' => 'right',
            'instanceId' => 'rail-right',
            'network' => 'arrow',
            'isR18Thread' => true,
        ])->render();

        $rail = route('ads.arrow', ['slot' => 'rail']);
        $this->assertStringContainsString('src="'.$rail.'"', $left);
        $this->assertStringContainsString('src="'.$rail.'"', $right);
        $this->assertStringContainsString('width:300px;height:250px', $left);
    }
}
