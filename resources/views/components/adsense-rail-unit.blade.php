@php
    $network = $network ?? 'gam';
    if ($network === 'adsense') {
        $network = 'gam';
    }
    $side = $side ?? 'left';
    $instanceId = $instanceId ?? 'rail-'.$side.'-'.uniqid();
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $name = \App\Services\LanguageService::trans('ad_network_'.$network, $lang);
    $isR18Context = !empty($isR18Thread) || !empty($searchIncludesR18);
    $adNetwork = app(\App\Services\AdNetworkService::class);
    $serveGam = $network === 'gam' && $adNetwork->shouldServeGamRail($side, $isR18Context, false);
    $serveExoclick = $network === 'exoclick' && $adNetwork->shouldServeExoclickRail($side, $isR18Context, false);
    $gamPath = $serveGam ? $adNetwork->gamSlotPath('rail') : null;
    $exoZone = $serveExoclick ? $adNetwork->exoclickZone('rail') : null;
    $serveLive = ($serveGam && $gamPath) || ($serveExoclick && $exoZone);
@endphp
<div class="adsense-rail-unit ad-slot ad-slot--rail ad-slot--{{ $network }}{{ $serveLive ? ' ad-slot--live' : '' }}"
     data-adsense-rail="{{ $side }}"
     data-ad-network="{{ $network }}"
     aria-label="{{ $name }}">
    @unless($serveLive)
        @include('components.ad-network-badge', ['network' => $network])
    @endunless
    @if($serveGam && $gamPath)
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
