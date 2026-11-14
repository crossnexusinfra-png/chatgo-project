<?php

namespace Tests\Unit;

use App\Services\AdNetworkService;
use Tests\TestCase;

class AdNetworkServiceTest extends TestCase
{
    private AdNetworkService $ads;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'ads.gam.enabled' => false,
            'ads.gam.network_code' => '',
            'ads.gam.slots.rail' => '',
            'ads.gam.slots.banner_l' => '',
            'ads.gam.slots.banner_m' => '',
            'ads.gam.slots.banner_s' => '',
            'ads.gam.slots.inline' => '',
            'ads.gam.slots.interstitial' => '',
            'ads.gam.slots.rewarded' => '',
            'ads.enabled' => false,
            'ads.arrow.enabled' => false,
            'ads.arrow.spots.banner_l' => '',
            'ads.arrow.spots.banner_m' => '',
            'ads.arrow.spots.banner_s' => '',
            'ads.arrow.spots.rail' => '',
        ]);

        $this->ads = new AdNetworkService();
    }

    public function test_empty_ids_keep_networks_disabled(): void
    {
        $this->assertFalse($this->ads->gamConfigured());
        $this->assertFalse($this->ads->arrowConfigured());
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', false, false));
        $this->assertFalse($this->ads->shouldLoadArrow(true, false));
        $this->assertSame([], array_filter($this->ads->extraCspSources()));
    }

    public function test_gam_slot_path_joins_network_code(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.rail' => 'chatgo_sfw_rail',
            'ads.gam.slots.inline' => '',
        ]);

        $this->assertTrue($this->ads->gamConfigured());
        $this->assertSame('/1234567/chatgo_sfw_rail', $this->ads->gamSlotPath('rail'));
        $this->assertTrue($this->ads->shouldLoadGpt('threads.show', false, false));
        $this->assertTrue($this->ads->shouldLoadGpt('legal.guide', false, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.index', false, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', true, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', false, true));
        $this->assertFalse($this->ads->shouldServeGamRail('left', true, false));
        $this->assertTrue($this->ads->shouldServeGamRail('left', false, false));
        $this->assertTrue($this->ads->shouldServeGamRail('right', false, false));
        $this->assertContains('https://securepubads.g.doubleclick.net', $this->ads->extraCspSources()['script-src']);
    }

    public function test_rewarded_slot_loads_gpt_on_coin_pages_only(): void
    {
        config([
            'ads.enabled' => true,
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.rewarded' => 'chatgo_sfw_rewarded',
        ]);

        $this->assertSame('/1234567/chatgo_sfw_rewarded', $this->ads->gamSlotPath('rewarded'));
        $this->assertTrue($this->ads->shouldServeGamRewarded(false, false));
        $this->assertFalse($this->ads->shouldServeGamRewarded(true, false));
        $this->assertTrue($this->ads->shouldLoadGpt('threads.index', false, false));
        $this->assertTrue($this->ads->shouldLoadGpt('threads.show', false, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', true, false));
        $this->assertFalse($this->ads->shouldLoadGpt('legal.guide', false, false));
    }

    public function test_inline_and_interstitial_slots_load_gpt_on_sfw_pages_only(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.inline' => 'chatgo_inline',
        ]);

        $this->assertSame('/1234567/chatgo_inline', $this->ads->gamSlotPath('inline'));
        $this->assertTrue($this->ads->shouldServeGamInline(false, false));
        $this->assertFalse($this->ads->shouldServeGamInline(true, false));
        $this->assertTrue($this->ads->shouldLoadGpt('threads.index', false, false));
        $this->assertTrue($this->ads->shouldLoadGpt('threads.search', false, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.search', false, true));
        $this->assertFalse($this->ads->shouldLoadGpt('legal.privacy', false, false));

        config(['ads.gam.slots.interstitial' => 'chatgo_interstitial']);

        $this->assertSame('/1234567/chatgo_interstitial', $this->ads->gamSlotPath('interstitial'));
        $this->assertTrue($this->ads->shouldLoadGpt('legal.privacy', false, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', true, false));
        $this->assertFalse($this->ads->shouldLoadGpt('admin.dashboard', false, false));
    }

    public function test_banner_sizes_replace_the_legacy_inline_unit(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.banner_l' => 'chatgo_sfw_banner_l',
            'ads.gam.slots.banner_m' => 'chatgo_sfw_banner_m',
            'ads.gam.slots.banner_s' => 'chatgo_sfw_banner_s',
            'ads.gam.slots.inline' => 'chatgo_sfw_inline',
        ]);

        $this->assertSame([
            'l' => '/1234567/chatgo_sfw_banner_l',
            'm' => '/1234567/chatgo_sfw_banner_m',
            's' => '/1234567/chatgo_sfw_banner_s',
        ], $this->ads->gamBannerPaths());
        $this->assertTrue($this->ads->shouldServeGamInline(false, false));
        $this->assertTrue($this->ads->shouldLoadGpt('threads.index', false, false));
        $this->assertFalse($this->ads->shouldServeGamInline(true, false));
    }

    public function test_arrow_is_used_on_adult_pages_and_gpt_is_not(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.rail' => 'chatgo_sfw_rail',
            'ads.arrow.enabled' => true,
            'ads.arrow.spots.banner_s' => '14108',
            'ads.arrow.spots.banner_m' => '14109',
            'ads.arrow.spots.banner_l' => '14110',
            'ads.arrow.spots.rail' => '14111',
        ]);

        $this->assertSame('arrow', $this->ads->adultDirectNetwork());
        $this->assertTrue($this->ads->shouldLoadGpt('threads.show', false, false));
        $this->assertFalse($this->ads->shouldLoadArrow(false, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', true, false));
        $this->assertTrue($this->ads->shouldLoadArrow(true, false));
        $this->assertTrue($this->ads->shouldLoadArrow(false, true));
        $this->assertSame(14108, $this->ads->arrowSlotSpec('s')['id']);
        $this->assertSame(300, $this->ads->arrowSlotSpec('rail')['width']);
        $this->assertSame(250, $this->ads->arrowSlotSpec('rail')['height']);
        $this->assertArrayHasKey('l', $this->ads->arrowInlineSources());
        $this->assertNotNull($this->ads->arrowRailSource());
        $this->assertContains("'self'", $this->ads->extraCspSources()['frame-src']);
        $this->assertNotContains('https://a.realsrv.com', $this->ads->extraCspSources()['script-src']);
    }
}
