@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('error_419_title', $lang) ?? '419 - セッション期限切れ' }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/error-pages.css') }}">
@endpush

@section('content')
<div class="error-page-container">
    <div class="error-page-content">
        <div class="error-code error-code-419">
            419
        </div>
        <h1 class="error-title">
            {{ \App\Services\LanguageService::trans('error_419_title', $lang) }}
        </h1>
        <p class="error-message">
            {{ \App\Services\LanguageService::trans('error_419_message', $lang) }}
        </p>
        <div class="error-actions">
            <button type="button" class="error-button-primary" id="error-page-reload">
                {{ \App\Services\LanguageService::trans('reload_page', $lang) }}
            </button>
            <a href="{{ route('threads.index') }}" class="error-button-secondary">
                {{ \App\Services\LanguageService::trans('back_to_home', $lang) }}
            </a>
        </div>
    </div>
</div>
<script nonce="{{ $csp_nonce ?? '' }}">
document.getElementById('error-page-reload')?.addEventListener('click', function() { window.location.reload(); });
</script>
@endsection
