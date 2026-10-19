@php
    $network = $network ?? 'adsense';
    $enabled = config('adsense.enabled');
    $client = config('adsense.client');
    $slot = config('adsense.slots.display_banner');
    $instanceId = $instanceId ?? 'banner-'.uniqid();
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $name = \App\Services\LanguageService::trans('ad_network_'.$network, $lang);
    $isR18Context = !empty($isR18Thread) || !empty($searchIncludesR18);
    $adNetwork = app(\App\Services\AdNetworkService::class);
    $serveAdsense = $network === 'adsense' && $enabled && $client && $slot && !$isR18Context;
    $serveExoclick = $network === 'exoclick' && $adNetwork->shouldServeExoclickInline($isR18Context, false);
    $exoZone = $serveExoclick ? $adNetwork->exoclickZone('inline') : null;
    $serveLive = $serveAdsense || ($serveExoclick && $exoZone);
@endphp
<div class="ad-slot ad-slot--inline ad-slot--{{ $network }}{{ $serveLive ? ' ad-slot--live' : '' }}"
     data-ad-network="{{ $network }}"
     data-adsense-inline="{{ $serveAdsense ? '1' : '0' }}"
     aria-label="{{ $name }}">
    @unless($serveLive)
        @include('components.ad-network-badge', ['network' => $network])
    @endunless
    @if($serveAdsense)
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ $client }}"
         data-ad-slot="{{ $slot }}"
         data-ad-format="horizontal"
         data-full-width-responsive="true"
         id="adsense-{{ e($instanceId) }}"></ins>
    @elseif($serveExoclick && $exoZone)
    <ins class="ad-slot-fill"
         data-zoneid="{{ e($exoZone) }}"
         data-exoclick-zone="1"></ins>
    @endif
</div>
