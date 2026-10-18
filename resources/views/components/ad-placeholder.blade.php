@php
    $network = $network ?? 'adsense';
    $format = $format ?? 'inline';
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $allowed = ['adsense', 'gam', 'exoclick'];
    if (! in_array($network, $allowed, true)) {
        $network = 'adsense';
    }
    $name = \App\Services\LanguageService::trans('ad_network_'.$network, $lang);
    $note = \App\Services\LanguageService::trans('ad_slot_note_'.$network, $lang);
@endphp
<div class="ad-slot-placeholder ad-slot-placeholder--{{ $format }} ad-slot--{{ $network }}"
     data-ad-network="{{ $network }}"
     aria-label="{{ $name }}">
    <span class="ad-slot-badge ad-slot-badge--{{ $network }}">{{ $name }}</span>
    <span class="ad-slot-note">{{ $note }}</span>
</div>
