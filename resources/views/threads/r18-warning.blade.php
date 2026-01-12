@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $hideSearch = true;
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('r18_thread_warning_title', $lang) }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/inline-styles.css') }}">
@endpush

@section('content')
<div class="main-container r18-warning-container">
    <div class="r18-warning-card">
        <h2 class="r18-warning-title">
            {{ \App\Services\LanguageService::trans('r18_thread_warning_title', $lang) }}
        </h2>
        
        <div class="r18-warning-content">
            <p class="r18-warning-paragraph">
                {{ \App\Services\LanguageService::trans('r18_thread_warning_message', $lang) }}
            </p>
            <p class="r18-warning-paragraph r18-warning-paragraph-bold">
                {{ \App\Services\LanguageService::trans('r18_thread_warning_confirm', $lang) }}
            </p>
        </div>
        
        <div class="r18-warning-actions">
            <a href="{{ route('threads.index') }}" class="btn btn-secondary r18-warning-button">
                {{ \App\Services\LanguageService::trans('cancel', $lang) }}
            </a>
            <form action="{{ route('threads.acknowledge', $thread->thread_id) }}" method="POST" class="form-inline">
                @csrf
                <button type="submit" class="btn btn-primary r18-warning-button">
                    {{ \App\Services\LanguageService::trans('r18_thread_acknowledge_button', $lang) }}
                </button>
            </form>
        </div>
    </div>
</div>
@endsection

