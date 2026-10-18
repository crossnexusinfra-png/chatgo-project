@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $variant = $variant ?? 'default';
    $showUsc2257 = !empty($showUsc2257);
    $showDmca = !empty($showDmca);
@endphp
@if($showUsc2257 || $showDmca)
<nav class="legal-compliance-links legal-compliance-links--{{ $variant }}" aria-label="{{ \App\Services\LanguageService::trans('r18_compliance_nav_label', $lang) }}">
    @if($showUsc2257)
        <a href="{{ route('legal.usc2257') }}">{{ \App\Services\LanguageService::trans('footer_usc2257', $lang) }}</a>
    @endif
    @if($showUsc2257 && $showDmca)
        <span class="legal-compliance-sep" aria-hidden="true">|</span>
    @endif
    @if($showDmca)
        <a href="{{ route('legal.dmca') }}">{{ \App\Services\LanguageService::trans('footer_dmca', $lang) }}</a>
    @endif
</nav>
@endif
