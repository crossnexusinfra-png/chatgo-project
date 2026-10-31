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
            'ads.gam.slots.inline' => '',
            'ads.gam.slots.interstitial' => '',
            'ads.gam.slots.rewarded' => '',
            'ads.enabled' => false,
            'ads.exoclick.enabled' => false,
            'ads.exoclick.zones.rail' => '',
            'ads.exoclick.zones.inline' => '',
        ]);

        $this->ads = new AdNetworkService();
    }

    public function test_empty_ids_keep_networks_disabled(): void
    {
        $this->assertFalse($this->ads->gamConfigured());
        $this->assertFalse($this->ads->exoclickConfigured());
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', false, false));
        $this->assertFalse($this->ads->shouldLoadExoclick(true, false));
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

    public function test_exoclick_only_on_adult_pages(): void
    {
        config([
            'ads.exoclick.enabled' => true,
            'ads.exoclick.zones.rail' => '111',
            'ads.exoclick.zones.inline' => '222',
        ]);

        $this->assertTrue($this->ads->exoclickConfigured());
        $this->assertSame('111', $this->ads->exoclickZone('rail'));
        $this->assertTrue($this->ads->shouldLoadExoclick(true, false));
        $this->assertTrue($this->ads->shouldLoadExoclick(false, true));
        $this->assertFalse($this->ads->shouldLoadExoclick(false, false));
        $this->assertTrue($this->ads->shouldServeExoclickInline(true, false));
        $this->assertTrue($this->ads->shouldServeExoclickRail('left', true, false));
        $this->assertTrue($this->ads->shouldServeExoclickRail('right', true, false));
        $this->assertFalse($this->ads->shouldServeExoclickRail('right', false, false));
        $this->assertContains('https://a.realsrv.com', $this->ads->extraCspSources()['script-src']);
    }

    public function test_gpt_and_exoclick_are_never_both_loaded(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.rail' => 'rail_left',
            'ads.exoclick.enabled' => true,
            'ads.exoclick.zones.inline' => '222',
        ]);

        $sfwGpt = $this->ads->shouldLoadGpt('threads.show', false, false);
        $sfwExo = $this->ads->shouldLoadExoclick(false, false);
        $this->assertTrue($sfwGpt);
        $this->assertFalse($sfwExo);

        $r18Gpt = $this->ads->shouldLoadGpt('threads.show', true, false);
        $r18Exo = $this->ads->shouldLoadExoclick(true, false);
        $this->assertFalse($r18Gpt);
        $this->assertTrue($r18Exo);
    }
}
