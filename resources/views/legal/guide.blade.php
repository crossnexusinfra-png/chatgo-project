@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $t = fn (string $key) => \App\Services\LanguageService::trans($key, $lang);
    $paragraphs = function (string $key) use ($t) {
        $lines = preg_split('/\R/u', $t($key)) ?: [];
        return array_values(array_filter(array_map('trim', $lines), fn ($line) => $line !== ''));
    };
@endphp

@section('title')
    {{ $t('guide_page_document_title') }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/thread-show.css') }}">
@endpush

@section('content')
    <x-content-with-rails instance-prefix="guide">
        <article class="legal-article">
            <h1 class="legal-article-title">{{ $t('guide_page_h1') }}</h1>

            <div class="legal-article-box">
            <section class="legal-article-section">
                @foreach ($paragraphs('guide_intro') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_register_title') }}</h2>
                @foreach ($paragraphs('guide_section_register_body') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_rooms_title') }}</h2>
                @foreach ($paragraphs('guide_section_rooms_body') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                <p>{{ $t('guide_section_rooms_examples_intro') }}</p>
                <ul class="legal-article-list">
                    @for ($i = 1; $i <= 5; $i++)
                        <li>{{ $t('guide_section_rooms_example_'.$i) }}</li>
                    @endfor
                </ul>
                <p>{{ $t('guide_section_rooms_examples_outro') }}</p>
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_join_title') }}</h2>
                @foreach ($paragraphs('guide_section_join_body') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_create_title') }}</h2>
                @foreach ($paragraphs('guide_section_create_body') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
                <p>{{ $t('guide_section_create_examples_intro') }}</p>
                <ul class="legal-article-list">
                    @for ($i = 1; $i <= 5; $i++)
                        <li>{{ $t('guide_section_create_example_'.$i) }}</li>
                    @endfor
                </ul>
                @foreach ($paragraphs('guide_section_create_outro') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_coins_title') }}</h2>
                <p>{{ $t('guide_section_coins_use_intro') }}</p>
                <ul class="legal-article-list">
                    @for ($i = 1; $i <= 2; $i++)
                        <li>{{ $t('guide_section_coins_use_'.$i) }}</li>
                    @endfor
                </ul>
                <p>{{ $t('guide_section_coins_earn_intro') }}</p>
                <ul class="legal-article-list">
                    @for ($i = 1; $i <= 3; $i++)
                        <li>{{ $t('guide_section_coins_earn_'.$i) }}</li>
                    @endfor
                </ul>
                <p>{{ $t('guide_section_coins_outro') }}</p>
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_translation_title') }}</h2>
                @foreach ($paragraphs('guide_section_translation_body') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_friends_title') }}</h2>
                @foreach ($paragraphs('guide_section_friends_body') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_report_title') }}</h2>
                <p>{{ $t('guide_section_report_intro') }}</p>
                <p>{{ $t('guide_section_report_actions_intro') }}</p>
                <ul class="legal-article-list">
                    @for ($i = 1; $i <= 4; $i++)
                        <li>{{ $t('guide_section_report_action_'.$i) }}</li>
                    @endfor
                </ul>
                @php $reportOutro = trim($t('guide_section_report_outro')); @endphp
                @if ($reportOutro !== '')
                    <p>{{ $reportOutro }}</p>
                @endif
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_safety_title') }}</h2>
                <p>{{ $t('guide_section_safety_intro') }}</p>
                <ul class="legal-article-list">
                    @for ($i = 1; $i <= 5; $i++)
                        <li>{{ $t('guide_section_safety_item_'.$i) }}</li>
                    @endfor
                </ul>
                <p>{{ $t('guide_section_safety_outro') }}</p>
            </section>

            <section class="legal-article-section">
                <h2>{{ $t('guide_section_help_title') }}</h2>
                <p>
                    {!! str_replace(
                        ':faq',
                        '<a href="'.e(route('legal.faq')).'">'.e($t('footer_faq')).'</a>',
                        e($t('guide_section_help_faq'))
                    ) !!}
                </p>
                <p>
                    {!! str_replace(
                        ':articles',
                        '<a href="'.e(route('legal.articles')).'">'.e($t('footer_articles')).'</a>',
                        e($t('guide_section_help_articles'))
                    ) !!}
                </p>
            </section>

            <section class="legal-article-section legal-article-section--closing">
                <h2>{{ $t('guide_section_start_title') }}</h2>
                @foreach ($paragraphs('guide_section_start_body') as $paragraph)
                    <p>{{ $paragraph }}</p>
                @endforeach
            </section>
            </div>
        </article>
    </x-content-with-rails>

    @if (config('adsense.enabled'))
        <script src="{{ asset('js/adsense-rail-refresh.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    @endif
@endsection
