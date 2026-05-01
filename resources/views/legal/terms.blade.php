@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('terms_page_document_title', $lang) }}</title>
    @include('layouts.favicon')
    <link rel="stylesheet" href="{{ asset('css/bbs.css') }}">
</head>
<body>
    <main class="main-content" style="max-width: 840px; margin: 0 auto; padding: 24px;">
        <h1>{{ \App\Services\LanguageService::trans('terms_page_h1', $lang) }}</h1>
        @for ($i = 1; $i <= 15; $i++)
            <section class="terms-privacy-block" style="margin-top: 20px;">
                <h2 class="terms-privacy-title">{{ \App\Services\LanguageService::trans('terms_art_' . $i . '_title', $lang) }}</h2>
                <div class="terms-privacy-text">{!! nl2br(\App\Services\LanguageService::trans('terms_art_' . $i . '_text', $lang)) !!}</div>
            </section>
        @endfor
    </main>
</body>
</html>
