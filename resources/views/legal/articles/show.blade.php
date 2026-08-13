@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $t = fn (string $key) => \App\Services\LanguageService::trans($key, $lang);
    $sections = $article['sections'] ?? [];
@endphp

@section('title')
    {{ $article['title'] }} | {{ $t('articles_page_h1') }} | Chatgo
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/thread-show.css') }}">
@endpush

@section('content')
    <x-content-with-rails instance-prefix="articles-show">
        <article class="legal-article">
            <p class="articles-back">
                <a href="{{ route('legal.articles') }}">{{ $t('articles_back_to_list') }}</a>
            </p>
            <h1 class="legal-article-title">{{ $article['title'] }}</h1>
            @if (!empty($article['published_at']))
                <p class="articles-show-date">{{ $article['published_at'] }}</p>
            @endif

            <div class="legal-article-box">
                @forelse ($sections as $section)
                    <section class="legal-article-section {{ empty($section['title']) ? 'legal-article-section--intro' : '' }}">
                        @if (!empty($section['title']))
                            <h2>{{ $section['title'] }}</h2>
                        @endif
                        @foreach ($section['blocks'] as $block)
                            @if (($block['type'] ?? '') === 'list')
                                <ul class="legal-article-list">
                                    @foreach ($block['items'] as $item)
                                        <li>{{ $item }}</li>
                                    @endforeach
                                </ul>
                            @elseif (($block['type'] ?? '') === 'paragraph')
                                <p class="{{ mb_strlen($block['text'] ?? '') <= 90 && preg_match('/^[「『"“].+[」』"”]$/u', $block['text'] ?? '') === 1 ? 'article-quote' : '' }}">{{ $block['text'] }}</p>
                            @endif
                        @endforeach
                    </section>
                @empty
                    <section class="legal-article-section">
                        <p>{{ $t('articles_body_coming_soon') }}</p>
                    </section>
                @endforelse
            </div>
        </article>
    </x-content-with-rails>

    @if (config('adsense.enabled'))
        <script src="{{ asset('js/adsense-rail-refresh.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    @endif
@endsection
