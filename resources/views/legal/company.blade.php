@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('company_page_document_title', $lang) }}</title>
    @include('layouts.favicon')
    <link rel="stylesheet" href="{{ asset('css/bbs.css') }}">
</head>
<body>
    <main class="main-content" style="max-width: 840px; margin: 0 auto; padding: 24px;">
        <h1>{{ \App\Services\LanguageService::trans('company_page_h1', $lang) }}</h1>
        <p>{{ \App\Services\LanguageService::trans('company_page_service_name', $lang) }}</p>
        <p>{{ \App\Services\LanguageService::trans('company_page_operator', $lang) }}</p>
        <p>{{ \App\Services\LanguageService::trans('company_page_contact', $lang) }}</p>
    </main>
</body>
</html>
