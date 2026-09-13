@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $footerVariant = $footerVariant ?? 'main';
@endphp
<footer class="site-footer site-footer-{{ $footerVariant }}">
    <a href="{{ route('legal.guide') }}">{{ \App\Services\LanguageService::trans('footer_guide', $lang) }}</a>
    <span> | </span>
    <a href="{{ route('legal.faq') }}">{{ \App\Services\LanguageService::trans('footer_faq', $lang) }}</a>
    <span> | </span>
    <a href="{{ route('legal.articles') }}">{{ \App\Services\LanguageService::trans('footer_articles', $lang) }}</a>
    <span> | </span>
    <a href="{{ route('legal.terms') }}">{{ \App\Services\LanguageService::trans('footer_terms', $lang) }}</a>
    <span> | </span>
    <a href="{{ route('legal.privacy') }}">{{ \App\Services\LanguageService::trans('footer_privacy', $lang) }}</a>
    <span> | </span>
    <a href="{{ route('legal.company') }}">{{ \App\Services\LanguageService::trans('footer_company', $lang) }}</a>
    <span> | </span>
    <a href="{{ route('legal.contact') }}">{{ \App\Services\LanguageService::trans('footer_contact', $lang) }}</a>
</footer>
