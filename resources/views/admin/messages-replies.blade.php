@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
@extends('layouts.app')

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
@endpush

@section('title')
    {{ \App\Services\LanguageService::trans('admin_messages_replies_title', $lang) }}
@endsection

@section('content')
<div class="admin-page">
    <div class="wrap">
        <p class="admin-messages-back-link">
            <a href="{{ route('admin.messages') }}" class="admin-link">← {{ \App\Services\LanguageService::trans('admin_messages_history_back_to_messages', $lang) }}</a>
        </p>
        <h1 class="admin-messages-title">{{ \App\Services\LanguageService::trans('admin_messages_replies_title', $lang) }}</h1>

        <div class="filter-section">
            <form method="get" action="{{ route('admin.messages.replies') }}" class="admin-messages-filter-form">
                <label class="admin-messages-filter-label">
                    <span>{{ \App\Services\LanguageService::trans('admin_sort_order', $lang) }}</span>
                    <select name="sort" class="admin-messages-select">
                        <option value="latest" {{ ($sort ?? 'latest') === 'latest' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_sort_latest', $lang) }}</option>
                        <option value="oldest" {{ ($sort ?? '') === 'oldest' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_sort_oldest', $lang) }}</option>
                    </select>
                </label>
                <label class="admin-messages-filter-checkbox-label">
                    <input type="checkbox" name="include_auto_replies" value="1" {{ !empty($includeAutoReplies) ? 'checked' : '' }}>
                    <span>{{ \App\Services\LanguageService::trans('admin_messages_include_auto_replies', $lang) }}</span>
                </label>
                <label class="admin-messages-filter-checkbox-label">
                    <input type="checkbox" name="include_template_replies" value="1" {{ !empty($includeTemplateReplies) ? 'checked' : '' }}>
                    <span>{{ \App\Services\LanguageService::trans('admin_messages_include_template_replies', $lang) }}</span>
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
                            {{ \App\Services\LanguageService::trans('admin_messages_reply_user', $lang) }}:
                            @php $copyToken = optional($m->user)->user_identifier ?? (!empty($m->user_id) ? (string) $m->user_id : '—'); @endphp
                            @if($copyToken !== '—')
                                <code class="admin-copy-token">{{ $copyToken }}</code>
                                <button type="button" class="admin-copy-btn" data-copy-text="{{ $copyToken }}">{{ \App\Services\LanguageService::trans('copy', $lang) }}</button>
                            @else
                                —
                            @endif
                            /
                            {{ \App\Services\LanguageService::trans('admin_messages_reply_source', $lang) }}:
                            <details>
                                <summary>{{ optional($m->parentMessage)->title ?? \App\Services\LanguageService::trans('notification_no_title', $lang) }}</summary>
                                <div class="admin-message">{{ optional($m->parentMessage)->body ?? '' }}</div>
                            </details>
                            / {{ \App\Services\LanguageService::trans('admin_messages_sent_at', $lang) }}
                            @if($m->published_at)<span data-utc-datetime="{{ $m->published_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $m->published_at->format('Y-m-d H:i') }}</span>@endif
                        </div>
                    </div>
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
<script nonce="{{ $csp_nonce ?? '' }}">
document.addEventListener('click', function(e) {
    const btn = e.target.closest('.admin-copy-btn');
    if (!btn) return;
    const text = btn.getAttribute('data-copy-text') || '';
    if (!text) return;
    const originalText = btn.textContent;
    navigator.clipboard.writeText(text).then(function() {
        btn.textContent = '{{ \App\Services\LanguageService::trans('copied', $lang) }}';
        setTimeout(function() { btn.textContent = originalText; }, 1200);
    });
});
</script>
@endsection
