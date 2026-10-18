@php
    $network = $network ?? 'adsense';
    $enabled = config('adsense.enabled');
    $client = config('adsense.client');
    $slot = config('adsense.slots.display_banner');
    $instanceId = $instanceId ?? 'banner-'.uniqid();
    $serveAdsense = $network === 'adsense' && $enabled && $client && $slot;
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $name = \App\Services\LanguageService::trans('ad_network_'.$network, $lang);
@endphp
<div class="ad-slot ad-slot--inline ad-slot--{{ $network }}"
     data-ad-network="{{ $network }}"
     data-adsense-inline="{{ $serveAdsense ? '1' : '0' }}"
     aria-label="{{ $name }}">
    @include('components.ad-network-badge', ['network' => $network])
    @if($serveAdsense)
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ $client }}"
         data-ad-slot="{{ $slot }}"
         data-ad-format="horizontal"
         data-full-width-responsive="true"
         id="adsense-{{ e($instanceId) }}"></ins>
    @endif
</div>
