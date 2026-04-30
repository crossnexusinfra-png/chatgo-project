@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('privacy_page_document_title', $lang) }}</title>
    @include('layouts.favicon')
    <link rel="stylesheet" href="{{ asset('css/bbs.css') }}">
</head>
<body>
    <main class="main-content" style="max-width: 840px; margin: 0 auto; padding: 24px;">
        <h1>{{ \App\Services\LanguageService::trans('privacy_page_h1', $lang) }}</h1>
        @php $privacyIntro = trim(\App\Services\LanguageService::trans('terms_privacy_intro', $lang)); @endphp
        @if($privacyIntro !== '')
            <p class="terms-privacy-intro">{{ $privacyIntro }}</p>
        @endif
        @for ($i = 1; $i <= 10; $i++)
            <section class="terms-privacy-block" style="margin-top: 20px;">
                <h2 class="terms-privacy-title">{{ \App\Services\LanguageService::trans('terms_privacy_' . $i . '_title', $lang) }}</h2>
                <div class="terms-privacy-text">{!! nl2br(\App\Services\LanguageService::trans('terms_privacy_' . $i . '_text', $lang)) !!}</div>
            </section>
        @endfor
        <p style="margin-top: 24px;">
            {{ \App\Services\LanguageService::trans('privacy_footer_before', $lang) }}<a href="{{ route('legal.contact') }}">{{ \App\Services\LanguageService::trans('privacy_footer_link', $lang) }}</a>{{ \App\Services\LanguageService::trans('privacy_footer_after', $lang) }}
        </p>
    </main>
</body>
</html>
