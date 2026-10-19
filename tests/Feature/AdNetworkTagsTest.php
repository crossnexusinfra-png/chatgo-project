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
            'ads.gam.slots.rail_left' => '',
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
            'ads.gam.slots.rail_left' => 'chatgo_rail_left',
            'ads.gam.slots.rail_right' => 'chatgo_rail_right',
            'ads.exoclick.enabled' => true,
            'ads.exoclick.zones.rail_left' => '111',
        ]);

        $response = $this->get('/en/guide');

        $response->assertOk();
        $response->assertSee('securepubads.g.doubleclick.net/tag/js/gpt.js', false);
        $response->assertSee('data-gpt-slot', false);
        $response->assertSee('/1234567/chatgo_rail_left', false);
        $response->assertDontSee('a.realsrv.com', false);
        $csp = (string) $response->headers->get('Content-Security-Policy');
        $this->assertStringContainsString('securepubads.g.doubleclick.net', $csp);
        $this->assertStringContainsString('a.realsrv.com', $csp);
    }
}
