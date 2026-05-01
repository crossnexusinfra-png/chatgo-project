@php
    $enabled = config('adsense.enabled');
    $client = config('adsense.client');
    $slot = config('adsense.slots.display_banner');
    $instanceId = $instanceId ?? 'banner-'.uniqid();
@endphp
<div class="adsense-inline-banner" data-adsense-inline="1">
    @if($enabled && $client && $slot)
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ $client }}"
         data-ad-slot="{{ $slot }}"
         data-ad-format="horizontal"
         data-full-width-responsive="true"
         id="adsense-{{ e($instanceId) }}"></ins>
    @else
    <div class="adsense-inline-banner-placeholder" aria-label="ad-placeholder">広告</div>
    @endif
</div>
