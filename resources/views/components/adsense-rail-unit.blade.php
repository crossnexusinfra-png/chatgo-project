@php
    $network = $network ?? 'gam';
    $enabled = config('adsense.enabled');
    $client = config('adsense.client');
    $side = $side ?? 'left';
    $slotKey = $side === 'right' ? 'rail_right' : 'rail_left';
    $slot = config('adsense.slots.'.$slotKey) ?: config('adsense.slots.display_banner');
    $instanceId = $instanceId ?? 'rail-'.$side.'-'.uniqid();
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $name = \App\Services\LanguageService::trans('ad_network_'.$network, $lang);
    $isR18Context = !empty($isR18Thread) || !empty($searchIncludesR18);
    $adNetwork = app(\App\Services\AdNetworkService::class);
    $serveAdsense = $network === 'adsense' && $enabled && $client && $slot && !$isR18Context;
    $serveGam = $network === 'gam' && $adNetwork->shouldServeGamRail($side, $isR18Context, false);
    $serveExoclick = $network === 'exoclick' && $adNetwork->shouldServeExoclickRail($side, $isR18Context, false);
    $gamPath = $serveGam ? $adNetwork->gamSlotPath($slotKey) : null;
    $exoZone = $serveExoclick ? $adNetwork->exoclickZone($slotKey) : null;
    $serveLive = $serveAdsense || ($serveGam && $gamPath) || ($serveExoclick && $exoZone);
@endphp
<div class="adsense-rail-unit ad-slot ad-slot--rail ad-slot--{{ $network }}{{ $serveLive ? ' ad-slot--live' : '' }}"
     data-adsense-rail="{{ $side }}"
     data-ad-network="{{ $network }}"
     aria-label="{{ $name }}">
    @unless($serveLive)
        @include('components.ad-network-badge', ['network' => $network])
    @endunless
    @if($serveAdsense)
    <ins class="adsbygoogle"
         style="display:block;"
         data-ad-client="{{ $client }}"
         data-ad-slot="{{ $slot }}"
         data-ad-format="vertical"
         data-full-width-responsive="false"
         id="adsense-{{ $instanceId }}"></ins>
    @elseif($serveGam && $gamPath)
    <div id="gpt-{{ e($instanceId) }}"
         class="ad-slot-fill"
         data-gpt-slot
         data-gpt-path="{{ e($gamPath) }}"
         data-gpt-size="rail"></div>
    @elseif($serveExoclick && $exoZone)
    <ins class="ad-slot-fill"
         data-zoneid="{{ e($exoZone) }}"
         data-exoclick-zone="1"></ins>
    @endif
</div>
