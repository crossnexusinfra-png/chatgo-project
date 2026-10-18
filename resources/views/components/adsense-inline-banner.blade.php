@php
    $network = $network ?? 'adsense';
    $enabled = config('adsense.enabled');
    $client = config('adsense.client');
    $slot = config('adsense.slots.display_banner');
    $instanceId = $instanceId ?? 'banner-'.uniqid();
    $serveAdsense = $network === 'adsense' && $enabled && $client && $slot;
@endphp
<div class="adsense-inline-banner ad-slot ad-slot--inline ad-slot--{{ $network }}" data-ad-network="{{ $network }}" data-adsense-inline="{{ $serveAdsense ? '1' : '0' }}">
    @if($serveAdsense)
    @include('components.ad-network-badge', ['network' => $network])
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ $client }}"
         data-ad-slot="{{ $slot }}"
         data-ad-format="horizontal"
         data-full-width-responsive="true"
         id="adsense-{{ e($instanceId) }}"></ins>
    @else
        @include('components.ad-placeholder', ['network' => $network, 'format' => 'inline'])
    @endif
</div>
