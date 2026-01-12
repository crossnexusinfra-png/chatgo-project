@php
        $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    @endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_messages_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <div class="wrap">
        <p class="admin-messages-back-link"><a href="{{ route('admin.dashboard') }}" class="admin-link">← {{ \App\Services\LanguageService::trans('back', $lang) }}</a></p>
        <h1 class="admin-messages-title">{{ \App\Services\LanguageService::trans('admin_messages_title', $lang) }}</h1>

    @if (session('success'))
            <div class="admin-messages-success">{{ session('success') }}</div>
    @endif

        <div class="card admin-card">
        <form method="post" action="{{ route('admin.messages.store') }}">
            @csrf
            <label>{{ \App\Services\LanguageService::trans('admin_messages_target', $lang) }}</label>
            <select name="audience" required>
                <option value="members">{{ \App\Services\LanguageService::trans('admin_messages_target_members', $lang) }}</option>
                <option value="guests">{{ \App\Services\LanguageService::trans('admin_messages_target_guests', $lang) }}</option>
            </select>
            <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}</label>
            <input type="text" name="title" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
            <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}</label>
            <textarea name="body" rows="5" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_body_placeholder_ja', $lang) }}" required></textarea>
                <div class="admin-messages-form-row">
                    <label class="admin-messages-label-flex">
                    <input type="checkbox" name="allows_reply" value="1" id="allows_reply">
                    <span>{{ \App\Services\LanguageService::trans('admin_messages_allow_reply', $lang) }}</span>
                </label>
            </div>
                <div class="admin-messages-form-row admin-messages-reply-limit-section" id="reply_limit_section">
                    <label class="admin-messages-label-flex">
                    <input type="radio" name="unlimited_reply" value="0" checked>
                        <span class="admin-messages-span-margin">{{ \App\Services\LanguageService::trans('admin_messages_reply_limit_once', $lang) }}</span>
                </label>
                    <label class="admin-messages-label-flex">
                    <input type="radio" name="unlimited_reply" value="1">
                    <span>{{ \App\Services\LanguageService::trans('admin_messages_reply_limit_unlimited', $lang) }}</span>
                </label>
            </div>
            <label>{{ \App\Services\LanguageService::trans('admin_messages_coin_amount', $lang) }}</label>
                <input type="number" name="coin_amount" min="0" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_coin_amount_placeholder', $lang) }}" class="admin-messages-input-full">
                <div class="admin-messages-submit-container">
                <button type="submit">{{ \App\Services\LanguageService::trans('admin_messages_send', $lang) }}</button>
            </div>
        </form>
    </div>

    <div class="filter-section">
            <h2 class="admin-messages-filter-title">{{ \App\Services\LanguageService::trans('admin_messages_sent_list', $lang) }}</h2>
            <form method="get" action="{{ route('admin.messages') }}" class="admin-messages-filter-form">
                <label class="admin-messages-filter-label">
                <span>{{ \App\Services\LanguageService::trans('admin_messages_filter', $lang) }}</span>
                    <select name="filter" class="admin-messages-select" onchange="this.form.submit()">
                    <option value="all" {{ ($filter ?? 'all') === 'all' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_all', $lang) }}</option>
                    <option value="report_auto_reply" {{ ($filter ?? '') === 'report_auto_reply' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_report_auto_reply', $lang) }}</option>
                    <option value="manual_reply" {{ ($filter ?? '') === 'manual_reply' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_manual_reply', $lang) }}</option>
                    <option value="report_auto" {{ ($filter ?? '') === 'report_auto' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_report_auto', $lang) }}</option>
                    <option value="members" {{ ($filter ?? '') === 'members' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_members', $lang) }}</option>
                    <option value="guests" {{ ($filter ?? '') === 'guests' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_guests', $lang) }}</option>
                </select>
            </label>
        </form>
    </div>
    @forelse($messages as $m)
        <div class="list-item">
                <div class="admin-messages-list-item-content">
                    <div class="admin-messages-list-item-left">
                        <div class="admin-messages-list-item-title">{{ $m->title ?? \App\Services\LanguageService::trans('notification_no_title', $lang) }}</div>
                        <div class="admin-messages-list-item-body">{{ $m->body }}</div>
                        <div class="admin-messages-list-item-meta">
                        @if($m->user_id)
                            {{ \App\Services\LanguageService::trans('admin_messages_sent_to', $lang) }}: {{ str_replace('{user_id}', $m->user_id, \App\Services\LanguageService::trans('admin_messages_sent_to_individual', $lang)) }}
                        @else
                            {{ \App\Services\LanguageService::trans('admin_messages_sent_to', $lang) }}: {{ $m->audience === 'members' ? \App\Services\LanguageService::trans('admin_messages_sent_to_members', $lang) : \App\Services\LanguageService::trans('admin_messages_sent_to_guests', $lang) }}
                        @endif
                        / {{ \App\Services\LanguageService::trans('admin_messages_sent_at', $lang) }} @if($m->published_at)<span data-utc-datetime="{{ $m->published_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $m->published_at->format('Y-m-d H:i') }}</span>@endif
                        @if($m->parent_message_id)
                                <span class="admin-messages-reply-indicator">{{ \App\Services\LanguageService::trans('admin_messages_reply_indicator', $lang) }}</span>
                        @endif
                    </div>
                </div>
                @if(!$m->parent_message_id)
                        <form method="post" action="{{ route('admin.messages.cancel', $m->id) }}" class="admin-messages-cancel-form" onsubmit="return confirm('{{ \App\Services\LanguageService::trans('admin_messages_cancel_confirm', $lang) }}');">
                        @csrf
                            <button type="submit" class="admin-messages-cancel-button">{{ \App\Services\LanguageService::trans('admin_messages_cancel', $lang) }}</button>
                    </form>
                @endif
            </div>
        </div>
    @empty
        <p>{{ \App\Services\LanguageService::trans('admin_messages_no_messages', $lang) }}</p>
    @endforelse
    </div>
</div>
    <script src="{{ asset('js/admin-messages.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endsection


