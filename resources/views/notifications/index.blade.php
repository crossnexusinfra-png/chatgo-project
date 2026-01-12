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
<link rel="stylesheet" href="{{ asset('css/notifications.css') }}">
@endpush

@section('content')
<div class="main-container notifications-container">
    <div class="notifications-header">
        <h1 class="notifications-title">{{ \App\Services\LanguageService::trans('notifications_title', $lang) }}</h1>
    </div>
    <div class="notifications-list" id="notificationsList">
        @forelse($messages as $m)
            @php
                $isUnread = !($m->is_read ?? false);
            @endphp
            <article 
                class="notification-item" 
                data-message-id="{{ $m->id }}"
                data-is-read="{{ $isUnread ? 'false' : 'true' }}"
                data-unlimited-reply="{{ ($m->unlimited_reply ?? false) ? 'true' : 'false' }}"
                onclick="toggleMessage({{ $m->id }}, this, event)"
            >
                <div class="notification-header">
                    @if($isUnread)
                        <span class="unread-mark"></span>
                    @endif
                    <div class="notification-content">
                        <h2 class="notification-title">
                            {{ $m->translated_title ?? $m->title ?? \App\Services\LanguageService::trans('notification_no_title', $lang) }}
                            @if($m->coin_amount && $m->coin_amount > 0)
                                <span class="coin-badge">{{ str_replace('{amount}', $m->coin_amount, \App\Services\LanguageService::trans('notification_coin_badge', $lang)) }}</span>
                            @endif
                        </h2>
                        <div class="notification-date">
                            @php
                                $dateTime = optional($m->published_at ?? $m->created_at);
                            @endphp
                            @if($dateTime)
                                <span data-utc-datetime="{{ $dateTime->format('Y-m-d H:i:s') }}" data-format="en">{{ $dateTime->format('Y-m-d H:i') }}</span>
                            @endif
                        </div>
                    </div>
                    <span class="toggle-icon">▼</span>
                </div>
                <div class="message-body"></div>
                @if($m->coin_amount && $m->coin_amount > 0 && auth()->check())
                    <div class="coin-reward-section" data-message-id="{{ $m->id }}" onclick="event.stopPropagation();">
                        @if($m->has_received_coin ?? false)
                            <div class="coin-received-message">{{ \App\Services\LanguageService::trans('notification_coin_already_received', $lang) }}</div>
                        @else
                            <button type="button" class="coin-receive-btn" onclick="event.stopPropagation(); receiveCoin(event, {{ $m->id }}, {{ $m->coin_amount }});">
                                {{ \App\Services\LanguageService::trans('notification_receive_coin', $lang) }}
                            </button>
                        @endif
                    </div>
                @endif
                @if(isset($m->title_key) && $m->title_key === 'r18_change_request_title' && $m->thread_id && !($m->reply_used ?? false))
                    <div class="r18-change-section" data-message-id="{{ $m->id }}" onclick="event.stopPropagation();">
                        <div class="r18-change-buttons" onclick="event.stopPropagation();">
                            <button type="button" class="r18-approve-btn" onclick="event.stopPropagation(); approveR18Change(event, {{ $m->id }});">
                                {{ \App\Services\LanguageService::trans('r18_change_approve_button', $lang) }}
                            </button>
                            <button type="button" class="r18-reject-btn" onclick="event.stopPropagation(); rejectR18Change(event, {{ $m->id }});">
                                {{ \App\Services\LanguageService::trans('r18_change_reject_button', $lang) }}
                            </button>
                        </div>
                    </div>
                @elseif(isset($m->allows_reply) && $m->allows_reply && (($m->unlimited_reply ?? false) || !($m->reply_used ?? false)))
                    <div class="reply-section" data-message-id="{{ $m->id }}" onclick="event.stopPropagation();">
                        <form class="reply-form" onsubmit="submitReply(event, {{ $m->id }})" onclick="event.stopPropagation();">
                            @csrf
                            <textarea name="reply_body" rows="3" placeholder="{{ \App\Services\LanguageService::trans('reply_placeholder', $lang) }}" class="reply-textarea" required onclick="event.stopPropagation();" onfocus="event.stopPropagation();"></textarea>
                            <div class="reply-submit-container" onclick="event.stopPropagation();">
                                <button type="submit" class="reply-submit-btn" onclick="event.stopPropagation();">{{ \App\Services\LanguageService::trans('reply_submit', $lang) }}</button>
                            </div>
                        </form>
                    </div>
                @endif
            </article>
        @empty
            <p class="notification-empty">{{ \App\Services\LanguageService::trans('notifications_empty', $lang) }}</p>
        @endforelse
    </div>
    
    @if($messages->hasMorePages())
        <div class="load-more-container">
            <button type="button" id="loadMoreBtn" class="load-more-btn" onclick="loadMoreNotifications()">
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
            'coin_amount' => $m->coin_amount ?? null,
            'has_received_coin' => ($m->has_received_coin ?? false),
            'title_key' => $m->title_key ?? null,
            'thread_id' => $m->thread_id ?? null,
        ];
    })->values();
@endphp
<script nonce="{{ $csp_nonce ?? '' }}">
    window.notificationsIndexConfig = {
        messagesData: @json($messagesData),
        csrfToken: document.querySelector('meta[name="csrf-token"]')?.content || '{{ csrf_token() }}',
        userId: {{ auth()->id() ?? 'null' }},
        currentPage: {{ $messages->currentPage() }},
        hasMorePages: {{ $messages->hasMorePages() ? 'true' : 'false' }},
        translations: {
            processing: '{{ \App\Services\LanguageService::trans("processing", $lang) }}',
            replyRequired: '{{ \App\Services\LanguageService::trans('reply_required', $lang) }}',
            replyFailed: '{{ \App\Services\LanguageService::trans('reply_failed', $lang) }}',
            replySuccess: '{{ \App\Services\LanguageService::trans('reply_success', $lang) }}',
            replySuccessMessage: '{{ \App\Services\LanguageService::trans('reply_success_message', $lang) }}',
            loginRequiredError: '{{ \App\Services\LanguageService::trans('login_required_error', $lang) }}',
            notificationCoinReceiveFailed: '{{ \App\Services\LanguageService::trans('notification_coin_receive_failed', $lang) }}',
            notificationReceiveCoin: '{{ \App\Services\LanguageService::trans('notification_receive_coin', $lang) }}',
            notificationCoinReceived: '{{ \App\Services\LanguageService::trans('notification_coin_received', $lang) }}',
            r18ChangeApproveFailed: '{{ \App\Services\LanguageService::trans('r18_change_approve_failed', $lang) }}',
            r18ChangeApproveButton: '{{ \App\Services\LanguageService::trans('r18_change_approve_button', $lang) }}',
            r18ChangeApproveSuccess: '{{ \App\Services\LanguageService::trans('r18_change_approve_success', $lang) }}',
            r18ChangeRejectFailed: '{{ \App\Services\LanguageService::trans('r18_change_reject_failed', $lang) }}',
            r18ChangeRejectButton: '{{ \App\Services\LanguageService::trans('r18_change_reject_button', $lang) }}',
            r18ChangeRejectSuccess: '{{ \App\Services\LanguageService::trans('r18_change_reject_success', $lang) }}',
            notificationsLoading: '{{ \App\Services\LanguageService::trans('notifications_loading', $lang) }}',
            notificationsLoadFailed: '{{ \App\Services\LanguageService::trans('notifications_load_failed', $lang) }}'
        }
    };
</script>
<script src="{{ asset('js/notifications-index.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endsection


