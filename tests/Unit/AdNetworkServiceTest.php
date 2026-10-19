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
            'ads.gam.slots.rail_left' => '',
            'ads.gam.slots.rail_right' => '',
            'ads.exoclick.enabled' => false,
            'ads.exoclick.zones.rail_left' => '',
            'ads.exoclick.zones.rail_right' => '',
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
            'ads.gam.slots.rail_left' => 'chatgo_rail_left',
            'ads.gam.slots.rail_right' => '/1234567/chatgo_rail_right',
        ]);

        $this->assertTrue($this->ads->gamConfigured());
        $this->assertSame('/1234567/chatgo_rail_left', $this->ads->gamSlotPath('rail_left'));
        $this->assertSame('/1234567/chatgo_rail_right', $this->ads->gamSlotPath('rail_right'));
        $this->assertTrue($this->ads->shouldLoadGpt('threads.show', false, false));
        $this->assertTrue($this->ads->shouldLoadGpt('legal.guide', false, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.index', false, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', true, false));
        $this->assertFalse($this->ads->shouldLoadGpt('threads.show', false, true));
        $this->assertFalse($this->ads->shouldServeGamRail('left', true, false));
        $this->assertTrue($this->ads->shouldServeGamRail('left', false, false));
        $this->assertContains('https://securepubads.g.doubleclick.net', $this->ads->extraCspSources()['script-src']);
    }

    public function test_exoclick_only_on_adult_pages(): void
    {
        config([
            'ads.exoclick.enabled' => true,
            'ads.exoclick.zones.rail_left' => '111',
            'ads.exoclick.zones.inline' => '222',
        ]);

        $this->assertTrue($this->ads->exoclickConfigured());
        $this->assertTrue($this->ads->shouldLoadExoclick(true, false));
        $this->assertTrue($this->ads->shouldLoadExoclick(false, true));
        $this->assertFalse($this->ads->shouldLoadExoclick(false, false));
        $this->assertTrue($this->ads->shouldServeExoclickInline(true, false));
        $this->assertFalse($this->ads->shouldServeExoclickRail('right', true, false));
        $this->assertContains('https://a.realsrv.com', $this->ads->extraCspSources()['script-src']);
    }

    public function test_gpt_and_exoclick_are_never_both_loaded(): void
    {
        config([
            'ads.gam.enabled' => true,
            'ads.gam.network_code' => '1234567',
            'ads.gam.slots.rail_left' => 'rail_left',
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
