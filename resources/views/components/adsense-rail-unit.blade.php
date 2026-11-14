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
    $serveArrow = $network === 'arrow' && $adNetwork->shouldServeArrowRail($side, $isR18Context, false);
    $gamPath = $serveGam ? $adNetwork->gamSlotPath('rail') : null;
    $arrowRailSrc = $serveArrow ? $adNetwork->arrowRailSource() : null;
    $serveLive = ($serveGam && $gamPath) || ($serveArrow && $arrowRailSrc);
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
    @elseif($serveArrow && $arrowRailSrc)
    <iframe class="ad-slot-fill arrow-slot"
            src="{{ e($arrowRailSrc) }}"
            title="ARROW"
            scrolling="no"
            referrerpolicy="strict-origin-when-cross-origin"
            style="width:300px;height:250px;border:0;display:block"></iframe>
    @endif
</div>
