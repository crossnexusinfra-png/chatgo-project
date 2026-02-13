@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('error_503_title', $lang) ?? '503 - サービス一時停止中' }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/error-pages.css') }}">
@endpush

@section('content')
<div class="error-page-container">
    <div class="error-page-content">
        <div class="error-code error-code-419">
            503
        </div>
        <h1 class="error-title">
            {{ \App\Services\LanguageService::trans('error_503_title', $lang) }}
        </h1>
        <p class="error-message">
            {{ \App\Services\LanguageService::trans('error_503_message', $lang) }}
        </p>
        <div class="error-actions">
            <a href="{{ route('threads.index') }}" class="error-button-primary">
                {{ \App\Services\LanguageService::trans('back_to_home', $lang) }}
            </a>
            <button type="button" class="error-button-secondary" id="error-page-reload">
                {{ \App\Services\LanguageService::trans('reload_page', $lang) }}
            </button>
        </div>
    </div>
</div>
<script nonce="{{ $csp_nonce ?? '' }}">
document.getElementById('error-page-reload')?.addEventListener('click', function() { window.location.reload(); });
</script>
@endsection
