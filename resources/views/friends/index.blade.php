@extends('layouts.app')

@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('friends', $lang) }}
@endsection

@section('content')
<div class="friends-container">
    <div class="friends-header">
        <h1>{{ \App\Services\LanguageService::trans('friends', $lang) }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    @if(session('error'))
        <div class="alert alert-danger">
            {{ session('error') }}
        </div>
    @endif

    @if(isset($isEnabled) && !$isEnabled)
        <!-- フレンド機能条件未達成の表示 -->
        <div class="friend-conditions-section">
            <h2>{{ \App\Services\LanguageService::trans('friend_feature_conditions_title', $lang) }}</h2>
            <div class="conditions-list">
                <div class="condition-item {{ $conditions['login_met'] ? 'condition-met' : 'condition-not-met' }}">
                    <span class="condition-icon">{{ $conditions['login_met'] ? '✓' : '✗' }}</span>
                    <span class="condition-text">
                        {{ \App\Services\LanguageService::trans('friend_login_count_label', $lang) }}: {{ $conditions['login_count'] }}/{{ $conditions['login_required'] }}{{ \App\Services\LanguageService::trans('days_unit', $lang) }}
                        @if(!$conditions['login_met'])
                            <span class="condition-remaining">（{{ str_replace(':days', $conditions['login_required'] - $conditions['login_count'], \App\Services\LanguageService::trans('days_remaining', $lang)) }}）</span>
                        @endif
                    </span>
                </div>
                <div class="condition-item {{ $conditions['thread_met'] ? 'condition-met' : 'condition-not-met' }}">
                    <span class="condition-icon">{{ $conditions['thread_met'] ? '✓' : '✗' }}</span>
                    <span class="condition-text">
                        {{ \App\Services\LanguageService::trans('friend_thread_count_label', $lang) }}: {{ $conditions['thread_count'] }}/{{ $conditions['thread_required'] }}{{ \App\Services\LanguageService::trans('items_unit', $lang) }}
                        @if(!$conditions['thread_met'])
                            <span class="condition-remaining">（{{ str_replace(':items', $conditions['thread_required'] - $conditions['thread_count'], \App\Services\LanguageService::trans('friend_items_remaining', $lang)) }}）</span>
                        @endif
                    </span>
                </div>
                <div class="condition-item {{ $conditions['response_met'] ? 'condition-met' : 'condition-not-met' }}">
                    <span class="condition-icon">{{ $conditions['response_met'] ? '✓' : '✗' }}</span>
                    <span class="condition-text">
                        {{ \App\Services\LanguageService::trans('friend_response_count_label', $lang) }}: {{ $conditions['response_count'] }}/{{ $conditions['response_required'] }}{{ \App\Services\LanguageService::trans('times_unit', $lang) }}
                        @if(!$conditions['response_met'])
                            <span class="condition-remaining">（{{ str_replace(':times', $conditions['response_required'] - $conditions['response_count'], \App\Services\LanguageService::trans('times_remaining', $lang)) }}）</span>
                        @endif
                    </span>
                </div>
            </div>
        </div>
    @endif

    <div class="friends-content">
        @if(isset($isEnabled) && !$isEnabled)
            <div class="alert alert-warning">
                {{ \App\Services\LanguageService::trans('friend_feature_conditions_message', $lang) }}
            </div>
        @endif
        @if(isset($isEnabled) && $isEnabled)
        <!-- 招待コードセクション -->
        <div class="invite-code-section">
            <h2>{{ \App\Services\LanguageService::trans('invite_code', $lang) }}</h2>
            <div class="invite-code-display">
                <input type="text" id="inviteCode" value="{{ $inviteCode }}" readonly>
                <button
                    type="button"
                    class="btn btn-primary js-copy-invite-code"
                    data-copied-label="{{ \App\Services\LanguageService::trans('copied', $lang) }}"
                >{{ \App\Services\LanguageService::trans('copy', $lang) }}</button>
            </div>
            <p class="invite-code-help">{{ \App\Services\LanguageService::trans('invite_code_help', $lang) }}</p>
        </div>
        @endif

        @if(isset($isEnabled) && $isEnabled)
        <!-- フレンド一覧 -->
        <div class="friends-list-section">
            <h2>{{ \App\Services\LanguageService::trans('friends_list', $lang) }} ({{ $friendCount }}/{{ $maxFriends }})</h2>
            @if($friendships->count() > 0)
                <div class="friends-list">
                    @foreach($friendships as $friendship)
                        @php
                            $friendId = $friendship->friend->user_id;
                            $status = $coinSendStatuses[$friendId] ?? ['can_send' => true, 'remaining_seconds' => 0, 'next_available_at' => null];
                            $peerPermaBanned = !empty($friendship->friend->is_permanently_banned);
                            $canSend = $status['can_send'] && !$peerPermaBanned;
                            $remainingSeconds = $status['remaining_seconds'];
                        @endphp
                        <div class="friend-item">
                            <div class="friend-info">
                                <a href="{{ route('profile.show', $friendId) }}" class="friend-link">
                                    {{ $friendship->friend->username . '@' . ($friendship->friend->user_identifier ?? $friendId) }}
                                </a>
                                @if($peerPermaBanned)
                                    <div class="friend-peer-perma-banned-notice">
                                        {{ \App\Services\LanguageService::trans('friend_peer_permanently_banned', $lang) }}
                                    </div>
                                @elseif(!$canSend && $status['next_available_at'])
                                    <div class="coin-send-wait-time" data-next-available="{{ $status['next_available_at'] ? $status['next_available_at']->timestamp : 0 }}" data-friend-id="{{ $friendId }}">
                                        <span class="wait-time-label">{{ \App\Services\LanguageService::trans('friend_next_send_available', $lang) }}: </span>
                                        <span class="wait-time-value" id="wait-time-{{ $friendId }}"></span>
                                    </div>
                                @endif
                            </div>
                            <div class="friend-actions">
                                <button 
                                    type="button"
                                    class="btn btn-primary js-send-coins-btn {{ !$canSend ? 'btn-disabled' : '' }}"
                                    data-friend-id="{{ $friendId }}"
                                    {{ !$canSend ? 'disabled' : '' }}
                                    id="send-coins-btn-{{ $friendId }}">
                                    {{ \App\Services\LanguageService::trans('send_coins', $lang) }}
                                </button>
                                <button type="button" class="btn btn-danger js-delete-friend-btn" data-friend-id="{{ $friendId }}">
                                    {{ \App\Services\LanguageService::trans('delete', $lang) }}
                                </button>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="no-friends">{{ \App\Services\LanguageService::trans('no_friends', $lang) }}</p>
            @endif
        </div>

        @endif

        @if(isset($isEnabled) && $isEnabled)
        <!-- フレンド申請可能なユーザー -->
        @if(isset($availableUsers) && count($availableUsers) > 0)
            <div class="friend-requests-section">
                <h2>{{ \App\Services\LanguageService::trans('available_friend_requests', $lang) }}</h2>
                @if(isset($isAtMaxFriendCount) && $isAtMaxFriendCount)
                    <div class="alert alert-warning">
                        {{ \App\Services\LanguageService::trans('max_friends_reached', $lang, ['max' => $maxFriends]) }}
                    </div>
                @elseif($isMaxFriendsReached)
                    <div class="alert alert-warning">
                        {{ \App\Services\LanguageService::trans('friend_slot_full_pending_requests_notice', $lang) }}
                    </div>
                @endif
                <div class="friend-requests-list">
                    @foreach($availableUsers as $available)
                        @php
                            $peerPermaBanned = !empty($available['user']->is_permanently_banned);
                        @endphp
                        <div class="friend-request-item">
                            <div class="friend-request-info">
                                <a href="{{ route('profile.show', $available['user']->user_id) }}" class="friend-link">
                                    {{ $available['user']->username . '@' . ($available['user']->user_identifier ?? $available['user']->user_id) }}
                                </a>
                                @if($peerPermaBanned)
                                    <div class="friend-peer-perma-banned-notice">
                                        {{ \App\Services\LanguageService::trans('friend_peer_permanently_banned', $lang) }}
                                    </div>
                                @endif
                                @if($available['sent_request'])
                                    <span class="status-pending">{{ \App\Services\LanguageService::trans('request_pending', $lang) }}</span>
                                @endif
                                @if($available['is_invite'])
                                    <span class="status-invite">{{ \App\Services\LanguageService::trans('invite_status', $lang) }}</span>
                                @endif
                            </div>
                            <div class="friend-request-actions">
                                @if($available['sent_request'])
                                    <button class="btn btn-secondary" disabled>
                                        {{ \App\Services\LanguageService::trans('request_pending', $lang) }}
                                    </button>
                                    <button type="button" class="btn btn-danger js-reject-available-btn" data-user-id="{{ $available['user']->user_id }}">
                                        {{ \App\Services\LanguageService::trans('reject', $lang) }}
                                    </button>
                                @elseif($available['received_request'])
                                    <form action="{{ route('friends.accept-request', $available['received_request']) }}" method="POST" class="form-inline friend-accept-request-form">
                                        @csrf
                                        <div class="js-friend-form-fields">
                                        <button type="submit" class="btn btn-success" {{ empty($available['can_accept']) ? 'disabled' : '' }}>{{ \App\Services\LanguageService::trans('accept', $lang) }}</button>
                                        </div>
                                    </form>
                                    <form action="{{ route('friends.reject-request', $available['received_request']) }}" method="POST" class="form-inline friend-reject-request-form" id="reject-form-{{ $available['received_request']->id }}">
                                        @csrf
                                        <button type="submit" class="btn btn-danger">{{ \App\Services\LanguageService::trans('reject', $lang) }}</button>
                                    </form>
                                @else
                                    <form action="{{ route('friends.send-request') }}" method="POST" class="form-inline friend-send-request-form">
                                        @csrf
                                        <input type="hidden" name="user_id" value="{{ $available['user']->user_id }}">
                                        <div class="js-friend-form-fields">
                                        <button type="submit" class="btn btn-primary" {{ ($isMaxFriendsReached || $peerPermaBanned) ? 'disabled' : '' }}>
                                            {{ \App\Services\LanguageService::trans('send_request', $lang) }}
                                        </button>
                                        </div>
                                    </form>
                                    <button type="button" class="btn btn-danger js-reject-available-btn" data-user-id="{{ $available['user']->user_id }}">
                                        {{ \App\Services\LanguageService::trans('reject', $lang) }}
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
         @endif
         @endif
    </div>
</div>

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/friends.css') }}">
@endpush

    <div
        id="friends-index-config"
        data-csrf-token="{{ csrf_token() }}"
        data-routes="{{ e(json_encode([
            'sendCoinsRoute' => route('friends.send-coins'),
            'deleteRoute' => route('friends.delete'),
            'rejectAvailableRoute' => route('friends.reject-available'),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
        data-translations="{{ e(json_encode([
            'submitting' => \App\Services\LanguageService::trans('submitting', $lang),
            'sending_request' => \App\Services\LanguageService::trans('sending_request', $lang),
            'deleting' => \App\Services\LanguageService::trans('deleting', $lang),
            'processing' => \App\Services\LanguageService::trans('processing', $lang),
            'copied' => \App\Services\LanguageService::trans('copied', $lang),
            'inviteCodeCopied' => \App\Services\LanguageService::trans('invite_code_copied', $lang),
            'errorOccurred' => \App\Services\LanguageService::trans('error_occurred', $lang),
            'hours' => \App\Services\LanguageService::trans('hours', $lang),
            'minutes' => \App\Services\LanguageService::trans('minutes', $lang),
            'seconds' => \App\Services\LanguageService::trans('seconds', $lang),
            'confirmDeleteFriend' => \App\Services\LanguageService::trans('confirm_delete_friend', $lang),
            'confirmRejectRequest' => \App\Services\LanguageService::trans('confirm_reject_request', $lang),
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES)) }}"
        hidden
    ></div>
    <script src="{{ asset('js/friends-index.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endsection

