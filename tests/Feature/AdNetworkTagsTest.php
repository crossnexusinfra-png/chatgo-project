<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdNetworkTagsTest extends TestCase
{
    public function test_guide_page_has_labeled_gam_slots_without_live_tags(): void
    {
        config([
            'ads.gam.enabled' => false,
            'ads.gam.network_code' => '',
            'ads.gam.slots.rail' => '',
            'ads.exoclick.enabled' => false,
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
            'ads.exoclick.enabled' => true,
            'ads.exoclick.zones.rail' => '111',
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
        $this->assertStringContainsString('a.realsrv.com', $csp);
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
            'ads.exoclick.enabled' => true,
            'ads.exoclick.zones.inline' => '222',
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

    public function test_inline_banner_renders_gam_slot_on_sfw_and_exoclick_on_r18(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.inline' => 'chatgo_inline',
            'adsense.enabled' => true,
            'adsense.client' => 'ca-pub-1438145064622040',
            'adsense.slots.display_banner' => '999',
            'ads.exoclick.enabled' => true,
            'ads.exoclick.zones.inline' => '222',
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

        $r18 = view('components.adsense-inline-banner', [
            'instanceId' => 'listing-r18',
            'network' => 'exoclick',
            'isR18Thread' => true,
        ])->render();

        $this->assertStringContainsString('data-ad-network="exoclick"', $r18);
        $this->assertStringContainsString('data-zoneid="222"', $r18);
        $this->assertStringNotContainsString('data-gpt-slot', $r18);
        $this->assertStringNotContainsString('adsbygoogle', $r18);
    }

    public function test_r18_rails_share_one_exoclick_zone(): void
    {
        config([
            'ads.exoclick.enabled' => true,
            'ads.exoclick.zones.rail' => '111',
        ]);

        $left = view('components.adsense-rail-unit', [
            'side' => 'left',
            'instanceId' => 'rail-left',
            'network' => 'exoclick',
            'isR18Thread' => true,
        ])->render();
        $right = view('components.adsense-rail-unit', [
            'side' => 'right',
            'instanceId' => 'rail-right',
            'network' => 'exoclick',
            'isR18Thread' => true,
        ])->render();

        $this->assertStringContainsString('data-zoneid="111"', $left);
        $this->assertStringContainsString('data-zoneid="111"', $right);
        $this->assertStringContainsString('data-adsense-rail="left"', $left);
        $this->assertStringContainsString('data-adsense-rail="right"', $right);
    }
}
