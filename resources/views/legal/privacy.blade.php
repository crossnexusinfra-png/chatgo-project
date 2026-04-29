@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>プライバシーポリシー | Chatgo</title>
    @include('layouts.favicon')
    <link rel="stylesheet" href="{{ asset('css/bbs.css') }}">
</head>
<body>
    <main class="main-content" style="max-width: 840px; margin: 0 auto; padding: 24px;">
        <h1>プライバシーポリシー</h1>
        <p class="terms-privacy-intro">{{ \App\Services\LanguageService::trans('terms_privacy_intro', $lang) }}</p>
        @for ($i = 1; $i <= 5; $i++)
            <section class="terms-privacy-block" style="margin-top: 20px;">
                <h2 class="terms-privacy-title">{{ \App\Services\LanguageService::trans('terms_privacy_' . $i . '_title', $lang) }}</h2>
                <div class="terms-privacy-text">{!! nl2br(e(\App\Services\LanguageService::trans('terms_privacy_' . $i . '_text', $lang))) !!}</div>
            </section>
        @endfor
        <p style="margin-top: 24px;">お問い合わせは <a href="{{ route('legal.contact') }}">お問い合わせページ</a> をご利用ください。</p>
    </main>
</body>
</html>
