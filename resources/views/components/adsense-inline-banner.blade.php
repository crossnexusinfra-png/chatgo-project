@php
    $enabled = config('adsense.enabled');
    $client = config('adsense.client');
    $slot = config('adsense.slots.display_banner');
    $instanceId = $instanceId ?? 'banner-'.uniqid();
@endphp
@if($enabled && $client && $slot)
<div class="adsense-inline-banner" data-adsense-inline="1">
    <ins class="adsbygoogle"
         style="display:block"
         data-ad-client="{{ $client }}"
         data-ad-slot="{{ $slot }}"
         data-ad-format="horizontal"
         data-full-width-responsive="true"
         id="adsense-{{ e($instanceId) }}"></ins>
</div>
@endif
