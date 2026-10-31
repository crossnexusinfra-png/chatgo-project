@php
    $network = $network ?? 'gam';
    if ($network === 'adsense') {
        $network = 'gam';
    }
    $side = $side ?? 'left';
    $instanceId = $instanceId ?? 'banner-'.uniqid();
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $name = \App\Services\LanguageService::trans('ad_network_'.$network, $lang);
    $isR18Context = !empty($isR18Thread) || !empty($searchIncludesR18);
    $adNetwork = app(\App\Services\AdNetworkService::class);
    $serveGam = $network === 'gam' && $adNetwork->shouldServeGamInline($isR18Context, false);
    $gamPath = $serveGam ? $adNetwork->gamSlotPath('inline') : null;
    $serveExoclick = $network === 'exoclick' && $adNetwork->shouldServeExoclickInline($isR18Context, false);
    $exoZone = $serveExoclick ? $adNetwork->exoclickZone('inline') : null;
    $serveLive = ($serveGam && $gamPath) || ($serveExoclick && $exoZone);
@endphp
<div class="ad-slot ad-slot--inline ad-slot--{{ $network }}{{ $serveLive ? ' ad-slot--live' : '' }}"
     data-ad-network="{{ $network }}"
     data-adsense-inline="0"
     aria-label="{{ $name }}">
    @unless($serveLive)
        @include('components.ad-network-badge', ['network' => $network])
    @endunless
    @if($serveGam && $gamPath)
    <div id="gpt-{{ e($instanceId) }}"
         class="ad-slot-fill"
         data-gpt-slot
         data-gpt-path="{{ e($gamPath) }}"
         data-gpt-size="inline"></div>
    @elseif($serveExoclick && $exoZone)
    <ins class="ad-slot-fill"
         data-zoneid="{{ e($exoZone) }}"
         data-exoclick-zone="1"></ins>
    @endif
</div>
