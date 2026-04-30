@extends('layouts.app')

@php
    // コントローラーから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $hideSearch = true;
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('notifications_title', $lang) }}
@endsection

@push('styles')
<link rel="stylesheet" href="{{ asset('css/notifications.css') }}?v={{ @filemtime(public_path('css/notifications.css')) ?: time() }}">
@endpush

@section('content')
<div class="notifications-container">
    <div class="notifications-header">
        <h1 class="notifications-title">{{ \App\Services\LanguageService::trans('notifications_title', $lang) }}</h1>
    </div>
    <div class="notifications-list" id="notificationsList">
        @include('notifications.partials.messages', ['messages' => $messages, 'lang' => $lang])
    </div>
    
    @if($messages->hasMorePages())
        <div class="load-more-container">
            <button type="button" id="loadMoreBtn" class="load-more-btn">
                {{ \App\Services\LanguageService::trans('notifications_load_more', $lang) }}
            </button>
        </div>
    @endif
</div>

@php
    $messagesData = $messages->map(function($m) {
        return [
            'id' => $m->id,
            'title' => $m->translated_title ?? $m->title ?? '',
            'body' => $m->translated_body ?? $m->body ?? '',
            'is_read' => ($m->is_read ?? false),
            'allows_reply' => ($m->allows_reply ?? false),
            'unlimited_reply' => ($m->unlimited_reply ?? false),
            'reply_used' => ($m->reply_used ?? false),
            'reply_used_by_user' => ($m->reply_used_by_user ?? false),
            'coin_amount' => $m->coin_amount ?? null,
            'has_received_coin' => ($m->has_received_coin ?? false),
            'title_key' => $m->title_key ?? null,
            'thread_id' => $m->thread_id ?? null,
            'report_ack_disabled' => ($m->report_ack_disabled ?? false),
            'requires_consent' => ($m->requires_consent_flag ?? false),
            'has_consented' => ($m->has_consented ?? false),
        ];
    })->values();
@endphp
<div
    id="notifications-index-config"
    data-messages-data="{{ e(json_encode($messagesData, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
    data-csrf-token="{{ csrf_token() }}"
    data-user-id="{{ auth()->id() ?? '' }}"
    data-current-page="{{ $messages->currentPage() }}"
    data-has-more-pages="{{ $messages->hasMorePages() ? '1' : '0' }}"
    data-translations="{{ e(json_encode([
        'processing' => \App\Services\LanguageService::trans('processing', $lang),
        'submitting' => \App\Services\LanguageService::trans('submitting', $lang),
        'replyRequired' => \App\Services\LanguageService::trans('reply_required', $lang),
        'replyFailed' => \App\Services\LanguageService::trans('reply_failed', $lang),
        'replySuccess' => \App\Services\LanguageService::trans('reply_success', $lang),
        'replySuccessMessage' => \App\Services\LanguageService::trans('reply_success_message', $lang),
        'loginRequiredError' => \App\Services\LanguageService::trans('login_required_error', $lang),
        'notificationCoinReceiveFailed' => \App\Services\LanguageService::trans('notification_coin_receive_failed', $lang),
        'notificationReceiveCoin' => \App\Services\LanguageService::trans('notification_receive_coin', $lang),
        'notificationCoinReceived' => \App\Services\LanguageService::trans('notification_coin_received', $lang),
        'r18ChangeApproveFailed' => \App\Services\LanguageService::trans('r18_change_approve_failed', $lang),
        'r18ChangeApproveButton' => \App\Services\LanguageService::trans('r18_change_approve_button', $lang),
        'r18ChangeApproveSuccess' => \App\Services\LanguageService::trans('r18_change_approve_success', $lang),
        'confirmR18ChangeApprove' => \App\Services\LanguageService::trans('confirm_r18_change_approve', $lang),
        'r18ChangeRejectFailed' => \App\Services\LanguageService::trans('r18_change_reject_failed', $lang),
        'r18ChangeRejectButton' => \App\Services\LanguageService::trans('r18_change_reject_button', $lang),
        'r18ChangeRejectSuccess' => \App\Services\LanguageService::trans('r18_change_reject_success', $lang),
        'reportAckButton' => \App\Services\LanguageService::trans('report_restriction_ack_button', $lang),
        'reportAckFailed' => \App\Services\LanguageService::trans('report_restriction_ack_failed', $lang),
        'reportAckSuccess' => \App\Services\LanguageService::trans('report_restriction_ack_success', $lang),
        'notificationsLoading' => \App\Services\LanguageService::trans('notifications_loading', $lang),
        'notificationsLoadFailed' => \App\Services\LanguageService::trans('notifications_load_failed', $lang),
        'mandatoryNoticeConsentButton' => \App\Services\LanguageService::trans('mandatory_notice_consent_button', $lang),
        'mandatoryNoticeConsentSuccess' => \App\Services\LanguageService::trans('mandatory_notice_consent_success', $lang),
        'mandatoryNoticeConsentFailed' => \App\Services\LanguageService::trans('mandatory_notice_consent_failed', $lang),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
    hidden
></div>
<script src="{{ asset('js/notifications-index.js') }}?v={{ @filemtime(public_path('js/notifications-index.js')) ?: time() }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endsection


