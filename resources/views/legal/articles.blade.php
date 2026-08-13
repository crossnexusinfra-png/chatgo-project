@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('articles_page_document_title', $lang) }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/thread-show.css') }}">
@endpush

@section('content')
    <x-content-with-rails instance-prefix="articles">
        <article class="legal-article">
            <h1 class="legal-article-title">{{ \App\Services\LanguageService::trans('articles_page_h1', $lang) }}</h1>
            <div class="legal-article-box">
                <section class="legal-article-section">
                    <p>{{ \App\Services\LanguageService::trans('articles_page_placeholder', $lang) }}</p>
                </section>
            </div>
        </article>
    </x-content-with-rails>

    @if (config('adsense.enabled'))
        <script src="{{ asset('js/adsense-rail-refresh.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    @endif
@endsection
