@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@section('title')
    {{ \App\Services\LanguageService::trans('thread_deleted_title', $lang) }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/bbs.css') }}">
@endpush

@section('content')
<div class="error-page-container">
    <div class="error-page-content">
        <div class="error-icon">🗑️</div>
        <h1 class="error-title">{{ \App\Services\LanguageService::trans('thread_deleted_title', $lang) }}</h1>
        <p class="error-message">{{ \App\Services\LanguageService::trans('thread_deleted_message', $lang) }}</p>
        <div class="error-actions">
            <a href="{{ route('threads.index') }}" class="btn-primary">{{ \App\Services\LanguageService::trans('back_to_home', $lang) }}</a>
        </div>
    </div>
</div>
@endsection
