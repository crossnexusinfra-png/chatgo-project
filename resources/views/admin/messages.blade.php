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
        <form method="post" action="{{ route('admin.messages.store') }}" id="admin-messages-form">
            @csrf
            @if(($templates ?? collect())->isNotEmpty())
                <label>テンプレート</label>
                <select id="message-template-select">
                    <option value="">テンプレートを選択してください</option>
                    @foreach($templates as $template)
                        <option value="{{ $template['key'] }}">{{ $template['name'] }}</option>
                    @endforeach
                </select>
            @endif
            <label>{{ \App\Services\LanguageService::trans('admin_messages_target_type', $lang) }}</label>
            <select name="target_type" id="target_type" required>
                <option value="all_members">{{ \App\Services\LanguageService::trans('admin_messages_target_all_members', $lang) }}</option>
                <option value="filtered">{{ \App\Services\LanguageService::trans('admin_messages_target_filtered', $lang) }}</option>
                <option value="specific">{{ \App\Services\LanguageService::trans('admin_messages_target_specific', $lang) }}</option>
            </select>
            <div id="target_filtered_fields" class="admin-messages-target-extra">
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
            <div id="target_specific_fields" class="admin-messages-target-extra">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_recipient_identifiers', $lang) }}</label>
                <textarea name="recipient_identifiers" id="recipient_identifiers" rows="3" placeholder="abc_user, def_id"></textarea>
            </div>
            <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（日本語）</label>
            <input type="text" name="title_ja" id="title_ja" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
            <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（英語）</label>
            <input type="text" name="title_en" id="title_en" placeholder="Title in English">
            <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（日本語）</label>
            <textarea name="body_ja" id="body_ja" rows="5" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_body_placeholder_ja', $lang) }}" required></textarea>
            <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（英語）</label>
            <textarea name="body_en" id="body_en" rows="5" placeholder="Message body in English"></textarea>
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

    <div class="card admin-card admin-collapsible-card">
        <button type="button" class="admin-collapsible-toggle" data-target-id="adminTemplateCreatePanel" aria-expanded="false">
            ▼ {{ \App\Services\LanguageService::trans('admin_messages_template_create_title', $lang) }}
        </button>
        <div id="adminTemplateCreatePanel" class="admin-collapsible-panel">
            <form method="post" action="{{ route('admin.messages.templates.store') }}">
                @csrf
                <label>{{ \App\Services\LanguageService::trans('admin_messages_template_name', $lang) }}</label>
                <input type="text" name="template_name" required maxlength="255" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_template_name_placeholder', $lang) }}">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（日本語）</label>
                <input type="text" name="template_title_ja" maxlength="255" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（英語）</label>
                <input type="text" name="template_title_en" maxlength="255" placeholder="Title in English">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（日本語）</label>
                <textarea name="template_body_ja" rows="4" required></textarea>
                <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（英語）</label>
                <textarea name="template_body_en" rows="4"></textarea>
                <label>{{ \App\Services\LanguageService::trans('admin_messages_coin_amount', $lang) }}</label>
                <input type="number" name="template_coin_amount" min="0" placeholder="0">
                <div class="admin-messages-submit-container">
                    <button type="submit">{{ \App\Services\LanguageService::trans('admin_messages_template_create_submit', $lang) }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card admin-card admin-collapsible-card admin-messages-welcome-section">
        <button type="button" class="admin-collapsible-toggle" data-target-id="adminWelcomeSettingPanel" aria-expanded="false">
            ▼ {{ \App\Services\LanguageService::trans('admin_messages_welcome_title', $lang) }}
        </button>
        <div id="adminWelcomeSettingPanel" class="admin-collapsible-panel">
            <form method="post" action="{{ route('admin.messages.set-welcome') }}">
                @csrf
                <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（日本語）</label>
                <input type="text" name="welcome_title_ja" value="{{ optional($welcomeMessage)->getAttributeValue('title_ja') ?? optional($welcomeMessage)->getAttributeValue('title') ?? '' }}" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（英語）</label>
                <input type="text" name="welcome_title_en" value="{{ optional($welcomeMessage)->getAttributeValue('title_en') ?? '' }}" placeholder="Title in English">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（日本語）</label>
                <textarea name="welcome_body_ja" rows="4" required>{{ optional($welcomeMessage)->getAttributeValue('body_ja') ?? optional($welcomeMessage)->getAttributeValue('body') ?? '' }}</textarea>
                <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（英語）</label>
                <textarea name="welcome_body_en" rows="4">{{ optional($welcomeMessage)->getAttributeValue('body_en') ?? '' }}</textarea>
                <label>{{ \App\Services\LanguageService::trans('admin_messages_coin_amount', $lang) }}</label>
                <input type="number" name="welcome_coin_amount" min="0" value="{{ optional($welcomeMessage)->coin_amount ?? 0 }}" placeholder="0">
                <div class="admin-messages-submit-container">
                    <button type="submit">{{ \App\Services\LanguageService::trans('admin_messages_welcome_save', $lang) }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="filter-section">
        <h2 class="admin-messages-filter-title">{{ \App\Services\LanguageService::trans('admin_messages_sent_list', $lang) }}</h2>
        <p class="admin-messages-back-link">
            <a href="{{ route('admin.messages.history') }}" class="admin-link">
                {{ \App\Services\LanguageService::trans('admin_messages_history_button', $lang) }}
            </a>
        </p>
    </div>
    </div>
</div>
    <script src="{{ asset('js/admin-messages.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    <script nonce="{{ $csp_nonce ?? '' }}">
        (function() {
            const templates = @json(collect($templates ?? [])->keyBy('key'));
            
            function applyTemplate(templateKey) {
                if (!templateKey || !templates[templateKey]) {
                    return;
                }
                
                const template = templates[templateKey];
                
                // フォームフィールドに値を設定
                const titleJaField = document.getElementById('title_ja');
                const bodyJaField = document.getElementById('body_ja');
                const coinAmountField = document.getElementById('coin_amount');
                
                if (titleJaField && template.title_ja) {
                    titleJaField.value = template.title_ja;
                }
                const titleEnField = document.getElementById('title_en');
                if (titleEnField && template.title_en) {
                    titleEnField.value = template.title_en;
                }
                
                if (bodyJaField && template.body_ja) {
                    bodyJaField.value = template.body_ja;
                }
                const bodyEnField = document.getElementById('body_en');
                if (bodyEnField && template.body_en) {
                    bodyEnField.value = template.body_en;
                }
                
                if (coinAmountField && template.coin_amount !== undefined) {
                    coinAmountField.value = template.coin_amount;
                }
            }
            
            // グローバルスコープに公開
            window.applyTemplate = applyTemplate;
            
            // target_type に応じて条件指定・特定ユーザー欄の表示切替（CSP対応: styleではなくclassで制御）
            function toggleTargetExtra() {
                const type = document.getElementById('target_type')?.value;
                const filteredEl = document.getElementById('target_filtered_fields');
                const specificEl = document.getElementById('target_specific_fields');
                if (filteredEl) {
                    filteredEl.classList.toggle('is-visible', type === 'filtered');
                }
                if (specificEl) {
                    specificEl.classList.toggle('is-visible', type === 'specific');
                }
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
                    document.querySelectorAll('.admin-collapsible-toggle').forEach(function(toggleBtn) {
                        toggleBtn.addEventListener('click', function() {
                            const panel = document.getElementById(toggleBtn.dataset.targetId);
                            if (!panel) return;
                            const isOpen = panel.classList.toggle('is-open');
                            toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                        });
                    });
                });
            } else {
                const templateSelect = document.getElementById('message-template-select');
                if (templateSelect) templateSelect.addEventListener('change', function() { applyTemplate(this.value); });
                const targetType = document.getElementById('target_type');
                if (targetType) {
                    targetType.addEventListener('change', toggleTargetExtra);
                    toggleTargetExtra();
                }
                document.querySelectorAll('.admin-collapsible-toggle').forEach(function(toggleBtn) {
                    toggleBtn.addEventListener('click', function() {
                        const panel = document.getElementById(toggleBtn.dataset.targetId);
                        if (!panel) return;
                        const isOpen = panel.classList.toggle('is-open');
                        toggleBtn.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
                    });
                });
            }
        })();
    </script>
@endsection


