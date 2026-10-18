@php
    $network = $network ?? 'adsense';
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $allowed = ['adsense', 'gam', 'exoclick'];
    if (! in_array($network, $allowed, true)) {
        $network = 'adsense';
    }
    $name = \App\Services\LanguageService::trans('ad_network_'.$network, $lang);
@endphp
<span class="ad-slot-badge ad-slot-badge--{{ $network }}" data-ad-network="{{ $network }}" aria-label="{{ $name }}">{{ $name }}</span>
