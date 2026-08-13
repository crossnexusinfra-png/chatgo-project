@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $t = fn (string $key) => \App\Services\LanguageService::trans($key, $lang);
    $articleGroups = $articleGroups ?? [];
@endphp

@section('title')
    {{ $t('articles_page_document_title') }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/thread-show.css') }}">
@endpush

@section('content')
    <x-content-with-rails instance-prefix="articles-index">
        <article class="legal-article">
            <h1 class="legal-article-title">{{ $t('articles_page_h1') }}</h1>
            <p class="faq-lead">{{ $t('articles_page_lead') }}</p>

            <div class="legal-article-box">
                @if (count($articleGroups) === 0)
                    <section class="legal-article-section">
                        <p>{{ $t('articles_page_empty') }}</p>
                    </section>
                @else
                    @foreach ($articleGroups as $group)
                        <section class="articles-index-category">
                            <h2 class="articles-index-category-title">{{ $group['title'] }}</h2>
                            <ul class="articles-index-list">
                                @foreach ($group['articles'] as $item)
                                    <li class="articles-index-item">
                                        <a href="{{ route('legal.articles.show', $item['slug']) }}" class="articles-index-link">
                                            <span class="articles-index-link-title">{{ $item['title'] }}</span>
                                            @if ($item['summary'] !== '')
                                                <span class="articles-index-link-summary">{{ $item['summary'] }}</span>
                                            @endif
                                        </a>
                                    </li>
                                @endforeach
                            </ul>
                        </section>
                    @endforeach
                @endif
            </div>
        </article>
    </x-content-with-rails>

    @if (config('adsense.enabled'))
        <script src="{{ asset('js/adsense-rail-refresh.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    @endif
@endsection
