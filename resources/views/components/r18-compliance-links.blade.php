@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $variant = $variant ?? 'default';
@endphp
<nav class="legal-compliance-links legal-compliance-links--{{ $variant }}" aria-label="{{ \App\Services\LanguageService::trans('r18_compliance_nav_label', $lang) }}">
    <a href="{{ route('legal.usc2257') }}">{{ \App\Services\LanguageService::trans('footer_usc2257', $lang) }}</a>
    <span class="legal-compliance-sep" aria-hidden="true">|</span>
    <a href="{{ route('legal.dmca') }}">{{ \App\Services\LanguageService::trans('footer_dmca', $lang) }}</a>
</nav>
