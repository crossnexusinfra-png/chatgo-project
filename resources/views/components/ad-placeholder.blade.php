@php
    $network = $network ?? 'adsense';
    $format = $format ?? 'inline';
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $allowed = ['adsense', 'gam', 'exoclick'];
    if (! in_array($network, $allowed, true)) {
        $network = 'adsense';
    }
    $name = \App\Services\LanguageService::trans('ad_network_'.$network, $lang);
@endphp
<div class="ad-slot ad-slot--{{ $format }} ad-slot--{{ $network }}"
     data-ad-network="{{ $network }}"
     aria-label="{{ $name }}">
    @include('components.ad-network-badge', ['network' => $network])
</div>
