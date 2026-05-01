@php
    $enabled = config('adsense.enabled');
    $client = config('adsense.client');
    $side = $side ?? 'left';
    $slotKey = $side === 'right' ? 'rail_right' : 'rail_left';
    $slot = config('adsense.slots.'.$slotKey) ?: config('adsense.slots.display_banner');
    $instanceId = $instanceId ?? 'rail-'.$side.'-'.uniqid();
@endphp
@if($enabled && $client && $slot)
<div class="adsense-rail-unit" data-adsense-rail="{{ $side }}">
    <div class="adsense-rail-refresh-wrap" data-adsense-rail-refresh="1" data-client="{{ $client }}" data-slot="{{ $slot }}">
        <ins class="adsbygoogle"
             style="display:block; min-height: 250px;"
             data-ad-client="{{ $client }}"
             data-ad-slot="{{ $slot }}"
             data-ad-format="vertical"
             data-full-width-responsive="false"
             id="adsense-{{ $instanceId }}"></ins>
    </div>
</div>
@endif
