@php
    $network = $network ?? 'gam';
    $enabled = config('adsense.enabled');
    $client = config('adsense.client');
    $side = $side ?? 'left';
    $slotKey = $side === 'right' ? 'rail_right' : 'rail_left';
    $slot = config('adsense.slots.'.$slotKey) ?: config('adsense.slots.display_banner');
    $instanceId = $instanceId ?? 'rail-'.$side.'-'.uniqid();
    $serveAdsense = $network === 'adsense' && $enabled && $client && $slot;
@endphp
<div class="adsense-rail-unit ad-slot ad-slot--rail ad-slot--{{ $network }}" data-adsense-rail="{{ $side }}" data-ad-network="{{ $network }}">
    @if($serveAdsense)
    <div class="adsense-rail-refresh-wrap" data-client="{{ $client }}" data-slot="{{ $slot }}">
        @include('components.ad-network-badge', ['network' => $network])
        <ins class="adsbygoogle"
             style="display:block;"
             data-ad-client="{{ $client }}"
             data-ad-slot="{{ $slot }}"
             data-ad-format="vertical"
             data-full-width-responsive="false"
             id="adsense-{{ $instanceId }}"></ins>
    </div>
    @else
    <div class="adsense-rail-refresh-wrap">
        @include('components.ad-placeholder', ['network' => $network, 'format' => 'rail'])
    </div>
    @endif
</div>
