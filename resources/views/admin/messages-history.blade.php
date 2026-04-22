@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $adminHistoryHasAutoSentColumn = \Illuminate\Support\Facades\Schema::hasColumn('admin_messages', 'is_auto_sent');
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_messages_history_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <div class="wrap">
        <p class="admin-messages-back-link">
            <a href="{{ route('admin.messages') }}" class="admin-link">← {{ \App\Services\LanguageService::trans('admin_messages_history_back_to_messages', $lang) }}</a>
        </p>
        <h1 class="admin-messages-title">{{ \App\Services\LanguageService::trans('admin_messages_history_title', $lang) }}</h1>

        <div class="filter-section">
            <form method="get" action="{{ route('admin.messages.history') }}" class="admin-messages-filter-form">
                <label class="admin-messages-filter-label">
                    <span>{{ \App\Services\LanguageService::trans('admin_messages_filter', $lang) }}</span>
                    <select name="filter" class="admin-messages-select">
                        <option value="all" {{ ($filter ?? 'all') === 'all' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_all', $lang) }}</option>
                        <option value="report_auto_reply" {{ ($filter ?? '') === 'report_auto_reply' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_report_auto_reply', $lang) }}</option>
                        <option value="manual_reply" {{ ($filter ?? '') === 'manual_reply' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_manual_reply', $lang) }}</option>
                        <option value="report_auto" {{ ($filter ?? '') === 'report_auto' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_report_auto', $lang) }}</option>
                        <option value="members" {{ ($filter ?? '') === 'members' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_members', $lang) }}</option>
                        <option value="specific" {{ ($filter ?? '') === 'specific' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_specific', $lang) }}</option>
                        <option value="guests" {{ ($filter ?? '') === 'guests' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_guests', $lang) }}</option>
                    </select>
                </label>
                <label class="admin-messages-filter-label">
                    <span>{{ \App\Services\LanguageService::trans('admin_sort_order', $lang) }}</span>
                    <select name="sort" class="admin-messages-select">
                        <option value="latest" {{ ($sort ?? 'latest') === 'latest' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_sort_latest', $lang) }}</option>
                        <option value="oldest" {{ ($sort ?? '') === 'oldest' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_sort_oldest', $lang) }}</option>
                    </select>
                </label>
                @if(!empty($adminHistoryHasAutoSentColumn))
                <label class="admin-messages-filter-checkbox-label">
                    <input type="checkbox" name="show_auto_sent" value="1" {{ !empty($showAutoSent) ? 'checked' : '' }}>
                    <span>{{ \App\Services\LanguageService::trans('admin_messages_show_auto_sent', $lang) }}</span>
                </label>
                @endif
                <label class="admin-messages-filter-checkbox-label">
                    <input type="checkbox" name="include_templates" value="1" {{ !empty($includeTemplates) ? 'checked' : '' }}>
                    <span>{{ \App\Services\LanguageService::trans('admin_messages_include_templates', $lang) }}</span>
                </label>
                <label class="admin-messages-filter-checkbox-label">
                    <input type="checkbox" name="reply_only" value="1" {{ !empty($replyOnly) ? 'checked' : '' }}>
                    <span>{{ \App\Services\LanguageService::trans('admin_messages_filter_reply_only', $lang) }}</span>
                </label>
                <button type="submit" class="btn btn-primary admin-messages-filter-submit">{{ \App\Services\LanguageService::trans('admin_apply', $lang) }}</button>
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
                            @elseif($m->recipients && $m->recipients->isNotEmpty())
                                {{ \App\Services\LanguageService::trans('admin_messages_sent_to', $lang) }}: {{ str_replace('{count}', $m->recipients->count(), \App\Services\LanguageService::trans('admin_messages_sent_to_specific', $lang)) }}
                            @else
                                {{ \App\Services\LanguageService::trans('admin_messages_sent_to', $lang) }}: {{ $m->audience === 'members' ? \App\Services\LanguageService::trans('admin_messages_sent_to_members', $lang) : \App\Services\LanguageService::trans('admin_messages_sent_to_guests', $lang) }}
                            @endif
                            / {{ \App\Services\LanguageService::trans('admin_messages_sent_at', $lang) }}
                            @if($m->published_at)<span data-utc-datetime="{{ $m->published_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $m->published_at->format('Y-m-d H:i') }}</span>@endif
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

        @if($messages->hasPages())
            <div class="admin-messages-submit-container">
                @if($messages->onFirstPage())
                    <span class="admin-link">←</span>
                @else
                    <a class="admin-link" href="{{ $messages->previousPageUrl() }}">←</a>
                @endif
                <span> {{ $messages->currentPage() }} / {{ $messages->lastPage() }} </span>
                @if($messages->hasMorePages())
                    <a class="admin-link" href="{{ $messages->nextPageUrl() }}">→</a>
                @else
                    <span class="admin-link">→</span>
                @endif
            </div>
        @endif
    </div>
</div>
@endsection

