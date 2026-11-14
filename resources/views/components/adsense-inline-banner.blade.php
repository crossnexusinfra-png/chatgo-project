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
    $bannerPaths = $serveGam ? $adNetwork->gamBannerPaths() : [];
    $gamPath = ($serveGam && $bannerPaths === []) ? $adNetwork->gamSlotPath('inline') : null;
    $serveArrow = $network === 'arrow' && $adNetwork->shouldServeArrowInline($isR18Context, false);
    $arrowSources = $serveArrow ? $adNetwork->arrowInlineSources() : [];
    $serveLive = ($serveGam && ($bannerPaths !== [] || $gamPath)) || ($serveArrow && $arrowSources !== []);
@endphp
<div class="ad-slot ad-slot--inline ad-slot--{{ $network }}{{ $serveLive ? ' ad-slot--live' : '' }}"
     data-ad-network="{{ $network }}"
     data-adsense-inline="0"
     aria-label="{{ $name }}">
    @unless($serveLive)
        @include('components.ad-network-badge', ['network' => $network])
    @endunless
    @if($serveGam && ($bannerPaths !== [] || $gamPath))
    <div id="gpt-{{ e($instanceId) }}"
         class="ad-slot-fill"
         data-gpt-slot
         @if($gamPath) data-gpt-path="{{ e($gamPath) }}" @endif
         @if(!empty($bannerPaths['l'])) data-gpt-path-l="{{ e($bannerPaths['l']) }}" @endif
         @if(!empty($bannerPaths['m'])) data-gpt-path-m="{{ e($bannerPaths['m']) }}" @endif
         @if(!empty($bannerPaths['s'])) data-gpt-path-s="{{ e($bannerPaths['s']) }}" @endif
         data-gpt-size="inline"></div>
    @elseif($serveArrow && $arrowSources !== [])
    <div class="ad-slot-fill"
         data-arrow-inline
         @if(!empty($arrowSources['l'])) data-arrow-src-l="{{ e($arrowSources['l']) }}" @endif
         @if(!empty($arrowSources['m'])) data-arrow-src-m="{{ e($arrowSources['m']) }}" @endif
         @if(!empty($arrowSources['s'])) data-arrow-src-s="{{ e($arrowSources['s']) }}" @endif></div>
    @endif
</div>
