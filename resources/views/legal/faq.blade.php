@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $t = fn (string $key) => \App\Services\LanguageService::trans($key, $lang);
    $paragraphs = function (string $key) use ($t) {
        $lines = preg_split('/\R/u', $t($key)) ?: [];

        return array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== ''));
    };
    $faqCount = 10;
@endphp

@section('title')
    {{ $t('faq_page_document_title') }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/thread-show.css') }}">
@endpush

@section('content')
    <x-content-with-rails instance-prefix="faq">
        <article class="legal-article faq-article">
            <h1 class="legal-article-title">{{ $t('faq_page_h1') }}</h1>
            <p class="faq-lead">{{ $t('faq_page_lead') }}</p>

            <div class="legal-article-box">
                <div class="faq-list">
                    @for ($i = 1; $i <= $faqCount; $i++)
                        @php
                            $answerParagraphs = $paragraphs('faq_item_'.$i.'_answer');
                            $listCountKey = 'faq_item_'.$i.'_list_count';
                            $listCountRaw = $t($listCountKey);
                            $listCount = (int) $listCountRaw;
                            if ($listCount < 0 || $listCount > 20 || $listCountRaw === $listCountKey) {
                                $listCount = 0;
                            }
                        @endphp
                        <section class="faq-item" id="faq-{{ $i }}">
                            <h2 class="faq-q">
                                <span class="faq-label">Q.</span>
                                <span class="faq-q-text">{{ $t('faq_item_'.$i.'_question') }}</span>
                            </h2>
                            <div class="faq-a">
                                <span class="faq-label faq-label--a">A.</span>
                                <div class="faq-a-body">
                                    @foreach ($answerParagraphs as $paragraph)
                                        @if ($i === 10)
                                            @php
                                                $withSuggestion = str_replace(
                                                    ':suggestion',
                                                    '<a href="'.e(route('threads.index')).'#suggestionForm">'.e($t('suggestion_title')).'</a>',
                                                    e($paragraph)
                                                );
                                                $withEmail = str_replace(
                                                    ':email',
                                                    '<a href="mailto:support@thecrossnexus.com">support@thecrossnexus.com</a>',
                                                    $withSuggestion
                                                );
                                            @endphp
                                            <p>{!! $withEmail !!}</p>
                                        @else
                                            <p>{{ $paragraph }}</p>
                                        @endif
                                    @endforeach

                                    @if ($listCount > 0)
                                        <ul class="legal-article-list faq-a-list">
                                            @for ($j = 1; $j <= $listCount; $j++)
                                                <li>{{ $t('faq_item_'.$i.'_list_'.$j) }}</li>
                                            @endfor
                                        </ul>
                                    @endif
                                </div>
                            </div>
                        </section>
                    @endfor
                </div>
            </div>
        </article>
    </x-content-with-rails>

    @if (config('adsense.enabled'))
        <script src="{{ asset('js/adsense-rail-refresh.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    @endif
@endsection
