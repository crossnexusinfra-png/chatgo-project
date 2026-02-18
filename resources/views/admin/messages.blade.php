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

        {{-- 初回登録時お知らせテンプレート設定 --}}
        <div class="card admin-card admin-messages-welcome-section">
            <h2 class="admin-messages-section-title">{{ \App\Services\LanguageService::trans('admin_messages_welcome_title', $lang) }}</h2>
            <form method="post" action="{{ route('admin.messages.set-welcome') }}">
                @csrf
                <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}</label>
                <input type="text" name="welcome_title" value="{{ optional($welcomeMessage)->title ?? '' }}" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}</label>
                <textarea name="welcome_body" rows="4" required>{{ optional($welcomeMessage)->body ?? '' }}</textarea>
                <label>{{ \App\Services\LanguageService::trans('admin_messages_coin_amount', $lang) }}</label>
                <input type="number" name="welcome_coin_amount" min="0" value="{{ optional($welcomeMessage)->coin_amount ?? 0 }}" placeholder="0">
                <div class="admin-messages-submit-container">
                    <button type="submit">{{ \App\Services\LanguageService::trans('admin_messages_welcome_save', $lang) }}</button>
                </div>
            </form>
        </div>

        <div class="card admin-card">
        <form method="post" action="{{ route('admin.messages.store') }}" id="admin-messages-form">
            @csrf
            @php
                $templates = config('admin.message_templates', []);
            @endphp
            @if(!empty($templates))
                <label>テンプレート</label>
                <select id="message-template-select">
                    <option value="">テンプレートを選択してください</option>
                    @foreach($templates as $key => $template)
                        <option value="{{ $key }}">{{ $template['name'] }}</option>
                    @endforeach
                </select>
            @endif
            <label>{{ \App\Services\LanguageService::trans('admin_messages_target_type', $lang) }}</label>
            <select name="target_type" id="target_type" required>
                <option value="all_members">{{ \App\Services\LanguageService::trans('admin_messages_target_all_members', $lang) }}</option>
                <option value="filtered">{{ \App\Services\LanguageService::trans('admin_messages_target_filtered', $lang) }}</option>
                <option value="specific">{{ \App\Services\LanguageService::trans('admin_messages_target_specific', $lang) }}</option>
            </select>
            <div id="target_filtered_fields" class="admin-messages-target-extra" style="display:none;">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_target_is_adult', $lang) }}</label>
                <select name="target_is_adult">
                    <option value="">{{ \App\Services\LanguageService::trans('admin_messages_target_is_adult_all', $lang) }}</option>
                    <option value="1">{{ \App\Services\LanguageService::trans('admin_messages_target_is_adult_yes', $lang) }}</option>
                    <option value="0">{{ \App\Services\LanguageService::trans('admin_messages_target_is_adult_no', $lang) }}</option>
                </select>
                <label>{{ \App\Services\LanguageService::trans('admin_messages_target_nationalities', $lang) }}</label>
                <input type="text" name="target_nationalities" placeholder="JP,US,GB">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_target_registered_after', $lang) }}</label>
                <input type="datetime-local" name="target_registered_after">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_target_registered_before', $lang) }}</label>
                <input type="datetime-local" name="target_registered_before">
            </div>
            <div id="target_specific_fields" class="admin-messages-target-extra" style="display:none;">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_recipient_identifiers', $lang) }}</label>
                <textarea name="recipient_identifiers" id="recipient_identifiers" rows="3" placeholder="user_identifier または 12345"></textarea>
            </div>
            <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}</label>
            <input type="text" name="title" id="title" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
            <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}</label>
            <textarea name="body" id="body" rows="5" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_body_placeholder_ja', $lang) }}" required></textarea>
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
                <input type="number" name="coin_amount" id="coin_amount" min="0" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_coin_amount_placeholder', $lang) }}" class="admin-messages-input-full">
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
                    <option value="specific" {{ ($filter ?? '') === 'specific' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('admin_messages_filter_specific', $lang) }}</option>
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
                        @elseif($m->recipients && $m->recipients->isNotEmpty())
                            {{ \App\Services\LanguageService::trans('admin_messages_sent_to', $lang) }}: {{ str_replace('{count}', $m->recipients->count(), \App\Services\LanguageService::trans('admin_messages_sent_to_specific', $lang)) }}
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
    <script nonce="{{ $csp_nonce ?? '' }}">
        (function() {
            const templates = @json(config('admin.message_templates', []));
            
            function applyTemplate(templateKey) {
                if (!templateKey || !templates[templateKey]) {
                    return;
                }
                
                const template = templates[templateKey];
                
                // フォームフィールドに値を設定
                const titleField = document.getElementById('title');
                const bodyField = document.getElementById('body');
                const coinAmountField = document.getElementById('coin_amount');
                
                if (titleField && template.title) {
                    titleField.value = template.title;
                }
                
                if (bodyField && template.body) {
                    bodyField.value = template.body;
                }
                
                if (coinAmountField && template.coin_amount !== undefined) {
                    coinAmountField.value = template.coin_amount;
                }
            }
            
            // グローバルスコープに公開
            window.applyTemplate = applyTemplate;
            
            // target_type に応じて条件指定・特定ユーザー欄の表示切替
            function toggleTargetExtra() {
                const type = document.getElementById('target_type')?.value;
                document.getElementById('target_filtered_fields').style.display = type === 'filtered' ? 'block' : 'none';
                document.getElementById('target_specific_fields').style.display = type === 'specific' ? 'block' : 'none';
                const ri = document.getElementById('recipient_identifiers');
                if (ri) ri.required = (type === 'specific');
            }
            if (document.readyState === 'loading') {
                document.addEventListener('DOMContentLoaded', function() {
                    const templateSelect = document.getElementById('message-template-select');
                    if (templateSelect) templateSelect.addEventListener('change', function() { applyTemplate(this.value); });
                    const targetType = document.getElementById('target_type');
                    if (targetType) {
                        targetType.addEventListener('change', toggleTargetExtra);
                        toggleTargetExtra();
                    }
                });
            } else {
                const templateSelect = document.getElementById('message-template-select');
                if (templateSelect) templateSelect.addEventListener('change', function() { applyTemplate(this.value); });
                const targetType = document.getElementById('target_type');
                if (targetType) {
                    targetType.addEventListener('change', toggleTargetExtra);
                    toggleTargetExtra();
                }
            }
        })();
    </script>
@endsection


