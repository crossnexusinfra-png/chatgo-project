@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_index_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <div class="admin-index-card">
        <h1 class="admin-index-title">{{ \App\Services\LanguageService::trans('admin_index_title', $lang) }}</h1>
        <p class="admin-index-description">{{ \App\Services\LanguageService::trans('admin_index_description', $lang) }}</p>
        <div class="admin-index-access-record">
            <div class="admin-index-access-record-title">{{ \App\Services\LanguageService::trans('admin_index_last_access', $lang) }}</div>
            <div>{{ \App\Services\LanguageService::trans('admin_index_last_guest', $lang) }}: {{ optional($lastGuest?->created_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
            <div>{{ \App\Services\LanguageService::trans('admin_index_last_login', $lang) }}: {{ optional($lastLogin?->created_at)->format('Y-m-d H:i:s') ?? '—' }}</div>
        </div>
        <ul class="admin-index-menu">
            <li>
                <a href="{{ route('admin.reports') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_reports', $lang) }}</a>
                @if(!empty($newReports))
                    <span class="admin-new-badge">NEW {{ $newReports }}</span>
                @endif
            </li>
            <li>
                <a href="{{ route('admin.suggestions') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_suggestions', $lang) }}</a>
                @if(!empty($newSuggestions))
                    <span class="admin-new-badge">NEW {{ $newSuggestions }}</span>
                @endif
            </li>
            <li><a href="{{ route('admin.messages') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_messages', $lang) }}</a></li>
            <li><a href="{{ route('admin.logs') }}" class="admin-link">{{ \App\Services\LanguageService::trans('admin_index_logs', $lang) }}</a></li>
        </ul>
    </div>
</div>
@endsection


