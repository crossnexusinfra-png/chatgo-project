<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdNetworkTagsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ads.arrow.enabled' => false,
            'ads.arrow.spots.banner_l' => '',
            'ads.arrow.spots.banner_m' => '',
            'ads.arrow.spots.banner_s' => '',
            'ads.arrow.spots.rail' => '',
        ]);
    }

    public function test_guide_page_has_labeled_gam_slots_without_live_tags(): void
    {
        config([
            'ads.gam.enabled' => false,
            'ads.gam.network_code' => '',
            'ads.gam.slots.rail' => '',
        ]);

        $response = $this->get('/en/guide');

        $response->assertOk();
        $response->assertSee('data-ad-network="gam"', false);
        $response->assertDontSee('securepubads.g.doubleclick.net', false);
        $response->assertDontSee('a.realsrv.com', false);
        $response->assertDontSee('data-gpt-slot', false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringNotContainsString('securepubads.g.doubleclick.net', $csp);
        $this->assertStringNotContainsString('a.realsrv.com', $csp);
    }

    public function test_guide_page_loads_gpt_when_gam_ids_are_configured(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.rail' => 'chatgo_sfw_rail',
            'adsense.enabled' => true,
            'adsense.client' => 'ca-pub-1438145064622040',
            'adsense.slots.display_banner' => '999',
        ]);

        $response = $this->get('/en/guide');

        $response->assertOk();
        $response->assertSee('securepubads.g.doubleclick.net/tag/js/gpt.js', false);
        $response->assertSee('data-gpt-slot', false);
        $response->assertSee('/1234567/chatgo_sfw_rail', false);
        $response->assertDontSee('pagead2.googlesyndication.com', false);
        $response->assertDontSee('a.realsrv.com', false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('securepubads.g.doubleclick.net', $csp);
        $this->assertStringNotContainsString('a.realsrv.com', $csp);
        $this->assertStringNotContainsString('exoclick.com', $csp);
    }

    public function test_sfw_page_loads_gpt_interstitial_without_adsense_tag(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.interstitial' => 'chatgo_interstitial',
            'adsense.enabled' => true,
            'adsense.client' => 'ca-pub-1438145064622040',
            'adsense.slots.display_banner' => '999',
            'adsense.slots.interstitial' => '888',
        ]);

        $response = $this->get('/en/guide');

        $response->assertOk();
        $response->assertSee('securepubads.g.doubleclick.net/tag/js/gpt.js', false);
        $response->assertSee('data-gpt-size="interstitial"', false);
        $response->assertSee('/1234567/chatgo_interstitial', false);
        $response->assertDontSee('pagead2.googlesyndication.com', false);
        $response->assertDontSee('adsbygoogle', false);
        $response->assertDontSee('a.realsrv.com/ad-provider.js', false);
    }

    public function test_inline_banner_renders_gam_slot_on_sfw_pages(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.banner_l' => '',
            'ads.gam.slots.banner_m' => '',
            'ads.gam.slots.banner_s' => '',
            'ads.gam.slots.inline' => 'chatgo_inline',
            'adsense.enabled' => true,
            'adsense.client' => 'ca-pub-1438145064622040',
            'adsense.slots.display_banner' => '999',
        ]);

        $sfw = view('components.adsense-inline-banner', [
            'instanceId' => 'listing-1',
            'network' => 'gam',
        ])->render();

        $this->assertStringContainsString('data-ad-network="gam"', $sfw);
        $this->assertStringContainsString('data-gpt-size="inline"', $sfw);
        $this->assertStringContainsString('/1234567/chatgo_inline', $sfw);
        $this->assertStringNotContainsString('adsbygoogle', $sfw);
        $this->assertStringNotContainsString('data-zoneid', $sfw);
        $this->assertStringNotContainsString('exoclick', $sfw);
    }

    public function test_inline_banner_uses_size_specific_units_when_configured(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '23386692786',
            'ads.gam.slots.banner_l' => 'chatgo_sfw_banner_l',
            'ads.gam.slots.banner_m' => 'chatgo_sfw_banner_m',
            'ads.gam.slots.banner_s' => 'chatgo_sfw_banner_s',
            'ads.gam.slots.inline' => 'chatgo_sfw_inline',
        ]);

        $html = view('components.adsense-inline-banner', [
            'instanceId' => 'listing-1',
            'network' => 'gam',
        ])->render();

        $this->assertStringContainsString('data-gpt-path-l="/23386692786/chatgo_sfw_banner_l"', $html);
        $this->assertStringContainsString('data-gpt-path-m="/23386692786/chatgo_sfw_banner_m"', $html);
        $this->assertStringContainsString('data-gpt-path-s="/23386692786/chatgo_sfw_banner_s"', $html);
        $this->assertStringNotContainsString('chatgo_sfw_inline', $html);
    }
}
