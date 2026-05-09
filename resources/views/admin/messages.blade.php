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
                <label>{{ \App\Services\LanguageService::trans('admin_messages_template_label', $lang) }}</label>
                <select id="message-template-select">
                    <option value="">{{ \App\Services\LanguageService::trans('admin_messages_template_select_placeholder', $lang) }}</option>
                    @foreach($templates as $template)
                        <option value="{{ $template['key'] }}">{{ $template['name'] }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="template_key" id="template_key" value="">
            @endif
            {{-- 送信先プルダウン（本番ソース検証用: admin_users_only が無ければ未デプロイ or viewキャッシュ） chatgo-recipient-ui-v2 --}}
            <label for="target_type">{{ \App\Services\LanguageService::trans('admin_messages_target_type', $lang) }}</label>
            <select name="target_type" id="target_type" required data-chatgo-recipient-ui="v2">
                <option value="all_members">{{ \App\Services\LanguageService::trans('admin_messages_target_all_members', $lang) }}</option>
                <option value="admin_users_only">{{ \App\Services\LanguageService::trans('admin_messages_target_admin_users_only', $lang) }}</option>
                <option value="filtered">{{ \App\Services\LanguageService::trans('admin_messages_target_filtered', $lang) }}</option>
                <option value="specific">{{ \App\Services\LanguageService::trans('admin_messages_target_specific', $lang) }}</option>
            </select>
            <div id="target_admin_only_hint" class="admin-messages-target-admin-hint" role="note">
                <p id="target_admin_only_hint_text" class="admin-messages-target-admin-hint-text">{{ \App\Services\LanguageService::trans('admin_messages_target_admin_users_only_help', $lang) }}</p>
            </div>
            @error('target_type')
                <p class="admin-form-error">{{ $message }}</p>
            @enderror
            <div id="target_filtered_fields" class="admin-messages-target-extra">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_target_is_adult', $lang) }}</label>
                <select name="target_is_adult">
                    <option value="">{{ \App\Services\LanguageService::trans('admin_messages_target_is_adult_all', $lang) }}</option>
                    <option value="1">{{ \App\Services\LanguageService::trans('admin_messages_target_is_adult_yes', $lang) }}</option>
                    <option value="0">{{ \App\Services\LanguageService::trans('admin_messages_target_is_adult_no', $lang) }}</option>
                </select>
                <label>{{ \App\Services\LanguageService::trans('admin_messages_target_nationalities', $lang) }}</label>
                <input type="text" name="target_nationalities" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_target_nationalities_example', $lang) }}">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_target_registered_after', $lang) }}</label>
                <input type="datetime-local" name="target_registered_after">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_target_registered_before', $lang) }}</label>
                <input type="datetime-local" name="target_registered_before">
            </div>
            <div id="target_specific_fields" class="admin-messages-target-extra">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_recipient_identifiers', $lang) }}</label>
                <textarea name="recipient_identifiers" id="recipient_identifiers" rows="3" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_recipient_identifiers_example', $lang) }}"></textarea>
            </div>
            <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_ja', $lang) }}）</label>
            <input type="text" name="title_ja" id="title_ja" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
            <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_en', $lang) }}）</label>
            <input type="text" name="title_en" id="title_en" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_en', $lang) }}">
            <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_ja', $lang) }}）</label>
            <textarea name="body_ja" id="body_ja" rows="5" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_body_placeholder_ja', $lang) }}" required></textarea>
            <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_en', $lang) }}）</label>
            <textarea name="body_en" id="body_en" rows="5" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_body_placeholder_en', $lang) }}"></textarea>
                @if(\Illuminate\Support\Facades\Schema::hasColumn('admin_messages', 'requires_consent'))
                <div class="admin-messages-form-row">
                    <label class="admin-messages-label-flex">
                        <input type="checkbox" name="requires_consent" value="1" id="requires_consent">
                        <span>{{ \App\Services\LanguageService::trans('admin_messages_requires_consent', $lang) }}</span>
                    </label>
                </div>
            @endif
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
            <form method="post" action="{{ route('admin.messages.templates.save') }}" id="adminTemplateEditorForm">
                @csrf
                <label>{{ \App\Services\LanguageService::trans('admin_messages_template_target', $lang) }}</label>
                <select id="template-editor-select">
                    <option value="new">{{ \App\Services\LanguageService::trans('admin_messages_template_new', $lang) }}</option>
                    @foreach(($editableTemplates ?? collect()) as $template)
                        <option value="{{ $template->id }}">{{ $template->name }}</option>
                    @endforeach
                </select>
                <input type="hidden" name="template_id" id="template_editor_id" value="">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_template_name', $lang) }}</label>
                <input type="text" name="template_name" id="template_editor_name" required maxlength="255" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_template_name_placeholder', $lang) }}">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_ja', $lang) }}）</label>
                <input type="text" name="template_title_ja" id="template_editor_title_ja" maxlength="255" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_en', $lang) }}）</label>
                <input type="text" name="template_title_en" id="template_editor_title_en" maxlength="255" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_en', $lang) }}">
                <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_ja', $lang) }}）</label>
                <textarea name="template_body_ja" id="template_editor_body_ja" rows="4" required></textarea>
                <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_en', $lang) }}）</label>
                <textarea name="template_body_en" id="template_editor_body_en" rows="4"></textarea>
                <div class="admin-template-edit-actions">
                    <button type="submit" id="template_editor_save_button">{{ \App\Services\LanguageService::trans('admin_messages_template_create_submit', $lang) }}</button>
                </div>
            </form>
            <form method="post" action="{{ route('admin.messages.templates.delete') }}" id="adminTemplateDeleteForm" data-confirm-message="{{ \App\Services\LanguageService::trans('admin_messages_template_delete_confirm', $lang) }}">
                @csrf
                <input type="hidden" name="template_id" id="template_delete_id" value="">
                <div class="admin-template-edit-actions">
                    <button type="submit" id="template_editor_delete_button" class="admin-messages-cancel-button" disabled>{{ \App\Services\LanguageService::trans('admin_messages_template_delete_submit', $lang) }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card admin-card admin-collapsible-card admin-messages-welcome-section">
        <button type="button" class="admin-collapsible-toggle" data-target-id="adminWelcomeSettingPanel" aria-expanded="false">
            ▼ {{ \App\Services\LanguageService::trans('admin_messages_welcome_title', $lang) }}
        </button>
        <div id="adminWelcomeSettingPanel" class="admin-collapsible-panel">
            @php
                $welcomeMessages = $welcomeMessages ?? ['normal' => null, 'google' => null, 'phone' => null];
            @endphp
            <form method="post" action="{{ route('admin.messages.set-welcome') }}">
                @csrf
                @foreach ([
                    'normal' => 'admin_messages_welcome_section_normal',
                    'google' => 'admin_messages_welcome_section_google',
                    'phone' => 'admin_messages_welcome_section_phone',
                ] as $welcomeType => $sectionLabelKey)
                    @php
                        $welcomeMessage = $welcomeMessages[$welcomeType] ?? null;
                    @endphp
                    <h3>{{ \App\Services\LanguageService::trans($sectionLabelKey, $lang) }}</h3>
                    <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_ja', $lang) }}）</label>
                    <input type="text" name="welcome_templates[{{ $welcomeType }}][title_ja]" value="{{ optional($welcomeMessage)->getAttributeValue('title_ja') ?? optional($welcomeMessage)->getAttributeValue('title') ?? '' }}" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_ja', $lang) }}">
                    <label>{{ \App\Services\LanguageService::trans('admin_messages_title_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_en', $lang) }}）</label>
                    <input type="text" name="welcome_templates[{{ $welcomeType }}][title_en]" value="{{ optional($welcomeMessage)->getAttributeValue('title_en') ?? '' }}" placeholder="{{ \App\Services\LanguageService::trans('admin_messages_title_placeholder_en', $lang) }}">
                    <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_ja', $lang) }}）</label>
                    <textarea name="welcome_templates[{{ $welcomeType }}][body_ja]" rows="4" required>{{ optional($welcomeMessage)->getAttributeValue('body_ja') ?? optional($welcomeMessage)->getAttributeValue('body') ?? '' }}</textarea>
                    <label>{{ \App\Services\LanguageService::trans('admin_messages_body_label', $lang) }}（{{ \App\Services\LanguageService::trans('admin_messages_title_en', $lang) }}）</label>
                    <textarea name="welcome_templates[{{ $welcomeType }}][body_en]" rows="4">{{ optional($welcomeMessage)->getAttributeValue('body_en') ?? '' }}</textarea>
                    <label>{{ \App\Services\LanguageService::trans('admin_messages_coin_amount', $lang) }}</label>
                    <input type="number" name="welcome_templates[{{ $welcomeType }}][coin_amount]" min="0" value="{{ optional($welcomeMessage)->coin_amount ?? 0 }}" placeholder="0">
                @endforeach
                <div class="admin-messages-submit-container">
                    <button type="submit">{{ \App\Services\LanguageService::trans('admin_messages_welcome_save', $lang) }}</button>
                </div>
            </form>
        </div>
    </div>

    <div class="filter-section">
        <h2 class="admin-messages-filter-title">{{ \App\Services\LanguageService::trans('admin_messages_sent_list', $lang) }}</h2>
        <p class="admin-messages-back-link">
            <a href="{{ route('admin.messages.replies') }}" class="admin-link">
                {{ \App\Services\LanguageService::trans('admin_messages_replies_button', $lang) }}
            </a>
        </p>
        <p class="admin-messages-back-link">
            <a href="{{ route('admin.messages.history') }}" class="admin-link">
                {{ \App\Services\LanguageService::trans('admin_messages_history_button', $lang) }}
            </a>
        </p>
    </div>
    </div>
</div>
    @php
        $sendTemplatesKeyed = collect($templates ?? [])->keyBy('key')->map(function ($row) {
            return [
                'key' => $row['key'],
                'name' => $row['name'],
                'title_ja' => $row['title_ja'] ?? null,
                'title_en' => $row['title_en'] ?? null,
                'body_ja' => $row['body_ja'] ?? '',
                'body_en' => $row['body_en'] ?? null,
            ];
        });
        $editableTemplatesKeyed = collect($editableTemplates ?? [])->keyBy('id')->map(function ($t) {
            return [
                'id' => $t->id,
                'name' => $t->name,
                'title_ja' => $t->title_ja,
                'title_en' => $t->title_en,
                'body_ja' => $t->body_ja,
                'body_en' => $t->body_en,
            ];
        });
        $adminMessagesTemplatesPayload = [
            'templates' => $sendTemplatesKeyed->isEmpty() ? new \stdClass() : $sendTemplatesKeyed->all(),
            'editableTemplates' => $editableTemplatesKeyed->isEmpty() ? new \stdClass() : $editableTemplatesKeyed->all(),
            'templateCreateLabel' => \App\Services\LanguageService::trans('admin_messages_template_create_submit', $lang),
            'templateUpdateLabel' => \App\Services\LanguageService::trans('admin_messages_template_update_submit', $lang),
        ];
    @endphp
    <script type="application/json" id="admin-messages-templates-data" nonce="{{ $csp_nonce ?? '' }}">@json($adminMessagesTemplatesPayload)</script>
    <script src="{{ asset('js/admin-messages.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    <script src="{{ asset('js/admin-messages-templates.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endsection


