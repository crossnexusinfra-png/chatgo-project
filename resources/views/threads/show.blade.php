@extends('layouts.app')

@php
    // コントローラーから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $hideSearch = true;
@endphp

@section('title')
    {{ $thread->title . \App\Services\LanguageService::trans('thread_detail_title', $lang ?? \App\Services\LanguageService::getCurrentLanguage()) }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/thread-show.css') }}">
@endpush

@section('content')
    <div class="chat-container">
        <!-- ヘッダー部分（固定） -->
        <header class="chat-header">
            <div class="chat-header-flex">
                <div class="back-link">
                    <a href="{{ route('threads.index') }}">{{ \App\Services\LanguageService::trans('back_to_threads', $lang) }}</a>
                </div>
                @if(isset($parentThread) && $parentThread || isset($continuationThread) && $continuationThread)
                <div class="thread-navigation-buttons-container">
                    @if(isset($parentThread) && $parentThread)
                    <a href="{{ route('threads.show', $parentThread) }}" class="thread-navigation-link-white">
                        ← {{ \App\Services\LanguageService::trans('continuation_thread_previous', $lang) }}
                    </a>
                    @endif
                    @if(isset($continuationThread) && $continuationThread)
                    <a href="{{ route('threads.show', $continuationThread) }}" class="thread-navigation-link-white">
                        {{ \App\Services\LanguageService::trans('continuation_thread_next', $lang) }} →
                    </a>
                    @endif
                </div>
                @endif
            </div>
            
            <div class="thread-title-header-flex">
                <h1 class="thread-title thread-title-main-flex">{{ $thread->getCleanTitle() }}</h1>
                @if(isset($continuationNumber) && $continuationNumber !== null)
                <span class="continuation-badge-large">
                    #{{ $continuationNumber }}
                </span>
                @endif
                @if(isset($isResponseLimitReached) && $isResponseLimitReached)
                <span class="completed-badge-large">
                    {{ \App\Services\LanguageService::trans('thread_completed', $lang) }}
                </span>
                @endif
            </div>
            
            <div class="thread-meta">
                <div class="meta-item">
                    @php
                        $threadUser = $users->get($thread->user_id);
                        $threadUsername = $threadUser ? $threadUser->username : '削除されたユーザー';
                        $threadDisplayUserName = $threadUsername;
                        if ($threadUser && $threadUser->user_identifier) {
                            $threadDisplayUserName = $threadUsername . '@' . $threadUser->user_identifier;
                        }
                        $isMyThread = auth()->check() && auth()->user()->user_id === ($threadUser->user_id ?? null);
                    @endphp
                    @if($threadUser)
                        @if($isMyThread)
                            <span class="user-link user-link-inline-flex">
                                @if($threadUser->profile_image)
                                    @php
                                        $imageUrl = (strpos($threadUser->profile_image, 'avatars/') !== false || strpos($threadUser->profile_image, 'images/avatars/') !== false)
                                            ? asset($threadUser->profile_image) 
                                            : asset('storage/' . $threadUser->profile_image);
                                    @endphp
                                    <img src="{{ $imageUrl }}" alt="{{ $threadUser->username }}" class="user-avatar">
                                @else
                                    <div class="user-avatar-placeholder">👤</div>
                                @endif
                                <span>{{ $threadDisplayUserName }}</span>
                            </span>
                        @else
                            <a href="{{ route('profile.show', $threadUser->user_id) }}" class="user-link">
                                @if($threadUser->profile_image)
                                    @php
                                        $imageUrl = (strpos($threadUser->profile_image, 'avatars/') !== false || strpos($threadUser->profile_image, 'images/avatars/') !== false)
                                            ? asset($threadUser->profile_image) 
                                            : asset('storage/' . $threadUser->profile_image);
                                    @endphp
                                    <img src="{{ $imageUrl }}" alt="{{ $threadUser->username }}" class="user-avatar">
                                @else
                                    <div class="user-avatar-placeholder">👤</div>
                                @endif
                                <span>{{ $threadDisplayUserName }}</span>
                            </a>
                        @endif
                    @else
                        <span>👤 {{ $threadDisplayUserName }}</span>
                    @endif
                </div>
                <div class="meta-item">
                    @php
                        $tag = $thread->tag ?? \App\Services\LanguageService::trans('other', $lang);
                        $translatedTag = \App\Services\LanguageService::transTag($tag, $lang);
                    @endphp
                    <span>🏷️ {{ $translatedTag }}</span>
                </div>
                <div class="meta-item">
                    <span>📅 <span data-utc-datetime="{{ $thread->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $thread->created_at->format('Y-m-d H:i') }}</span></span>
                </div>
                @auth
                <div class="meta-item">
                    @if(isset($userReportedThread) && $userReportedThread)
                        @if(isset($userReportedThreadRejected) && $userReportedThreadRejected)
                            <span class="report-btn reported-badge">{{ \App\Services\LanguageService::trans('reported', $lang) }}</span>
                        @else
                            <button type="button" class="report-btn" onclick="openReportModal({{ $thread->thread_id }}, null)">{{ \App\Services\LanguageService::trans('report_change', $lang) }}</button>
                        @endif
                    @else
                        <button type="button" class="report-btn" onclick="openReportModal({{ $thread->thread_id }}, null)">{{ \App\Services\LanguageService::trans('report', $lang) }}</button>
                    @endif
                </div>
                <div class="meta-item">
                    <form action="{{ route('threads.favorite.toggle', $thread) }}" method="POST">
                        @csrf
                        <button type="submit" class="favorite-btn">{{ $isFavorited ? \App\Services\LanguageService::trans('unfavorite', $lang) : \App\Services\LanguageService::trans('favorite', $lang) }}</button>
                    </form>
                </div>
                @endauth
            </div>

        </header>

        <!-- 検索欄 -->
        <div class="search-container">
            <div class="search-input-wrapper">
                <input type="text" id="searchInput" class="search-input" placeholder="{{ \App\Services\LanguageService::trans('search_responses_placeholder', $lang) }}">
                <span class="search-icon">🔍</span>
            </div>
            <div id="searchResults" class="search-results"></div>
            <div class="search-options">
                <div class="search-option">
                    <input type="radio" id="searchTargetBody" name="searchTarget" value="body" checked>
                    <label for="searchTargetBody">{{ \App\Services\LanguageService::trans('search_target_body', $lang) }}</label>
                </div>
                <div class="search-option">
                    <input type="radio" id="searchTargetUser" name="searchTarget" value="user">
                    <label for="searchTargetUser">{{ \App\Services\LanguageService::trans('search_target_user', $lang) }}</label>
                </div>
                <div class="search-option">
                    <input type="radio" id="searchTargetBoth" name="searchTarget" value="both">
                    <label for="searchTargetBoth">{{ \App\Services\LanguageService::trans('search_target_both', $lang) }}</label>
                </div>
            </div>
        </div>

        <!-- レスポンス一覧（スクロール可能）または警告メッセージ -->
        @if(!empty($isThreadDeletedByReport))
            <div class="responses-container thread-restriction-warning-container">
                <div class="thread-restriction-warning-content">
                    <h2 class="thread-restriction-warning-title">{{ \App\Services\LanguageService::trans('thread_deleted_by_report', $lang) }}</h2>
                </div>
            </div>
        @elseif(isset($isThreadRestricted) && $isThreadRestricted && !session('acknowledged_thread_' . $thread->thread_id))
            <!-- 了承前は警告メッセージをレスポンス表示部分全体に表示 -->
            <div class="responses-container thread-restriction-warning-container">
                <div class="thread-restriction-warning-content">
                    <h2 class="thread-restriction-warning-title">{{ \App\Services\LanguageService::trans('thread_restricted_warning', $lang) }}</h2>
                    <p class="thread-restriction-warning-text">
                        {{ \App\Services\LanguageService::trans('thread_restricted_description', $lang) }}
                    </p>
                    <ul class="thread-restriction-reasons-list">
                        @if(!empty($threadRestrictionReasons))
                            @foreach($threadRestrictionReasons as $reason)
                                <li>{{ $reason }}</li>
                            @endforeach
                        @else
                            <li>{{ \App\Services\LanguageService::trans('restricted_by_report', $lang) }}</li>
                        @endif
                    </ul>
                    <form action="{{ route('threads.acknowledge', $thread) }}" method="POST" class="thread-restriction-form">
                        @csrf
                        <button type="submit" class="thread-restriction-acknowledge-btn">
                            {{ \App\Services\LanguageService::trans('acknowledge_restriction', $lang) }}
                        </button>
                    </form>
                </div>
            </div>
        @else
        <div class="responses-container" id="responsesContainer">
            @if (session('success'))
                <div class="alert alert-success">
                    {{ session('success') }}
                </div>
            @endif

            @if ($errors->any() && !$errors->has('body'))
                <div class="alert alert-danger">
                    <ul>
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @forelse ($thread->responses as $response)
                @php
                    $isReported = isset($userReportedResponses) && $userReportedResponses->contains($response->response_id);
                    $isReportRejected = isset($userReportedResponseRejected) && isset($userReportedResponseRejected[$response->response_id]) && $userReportedResponseRejected[$response->response_id];
                @endphp
                @include('threads.partials.response-item', [
                    'response' => $response, 
                    'users' => $users, 
                    'thread' => $thread,
                    'isReported' => $isReported ?? false,
                    'isReportRejected' => $isReportRejected ?? false,
                    'lang' => $lang
                ])
            @empty
                <div class="no-responses">
                    <p>{{ \App\Services\LanguageService::trans('no_responses_yet', $lang) }}</p>
                    <p>{{ \App\Services\LanguageService::trans('first_response_hint', $lang) }}</p>
                </div>
            @endforelse
        </div>
        @endif

        <!-- 検索結果表示エリア -->
        <div class="search-results-area" id="searchResultsArea">
            <div class="search-results-list" id="searchResultsList">
                <!-- 検索結果がここに表示されます -->
            </div>
        </div>

        <!-- レスポンス送信フォーム（固定） -->
        <section class="chat-input">
            @if(isset($isThreadRestricted) && $isThreadRestricted)
                @if(!session('acknowledged_thread_' . $thread->thread_id))
                    <div class="thread-restriction-info">
                        <p>{{ \App\Services\LanguageService::trans('thread_restricted_info_acknowledge', $lang) }}</p>
                    </div>
                @else
                    <div class="thread-restriction-info">
                        <p>{{ \App\Services\LanguageService::trans('thread_restricted_info_view_only', $lang) }}</p>
                    </div>
                @endif
            @else
            @auth
                @if(isset($isResponseLimitReached) && $isResponseLimitReached && !isset($continuationThread))
                <!-- 続きスレッド要望アンケート -->
                <div id="continuation-request-panel" class="continuation-request-panel">
                    <h3 class="continuation-request-title">
                        {{ \App\Services\LanguageService::trans('continuation_request_title', $lang) }}
                    </h3>
                    <p class="continuation-request-description">
                        {{ \App\Services\LanguageService::trans('continuation_request_description', $lang) }}
                    </p>
                    <div class="continuation-request-buttons-container">
                        @php
                            $isLimitReached = isset($isContinuationRequestLimitReached) && $isContinuationRequestLimitReached;
                            $isThreadOwner = isset($isCurrentUserThreadOwner) && $isCurrentUserThreadOwner;
                            // スレッド主の場合は上限に関係なく要望できる
                            $canRequest = $isThreadOwner 
                                ? true 
                                : (!$isLimitReached || (isset($hasUserContinuationRequest) && $hasUserContinuationRequest));
                            // ボタンのクラスを決定
                            $buttonClass = 'continuation-request-button';
                            if (isset($hasUserContinuationRequest) && $hasUserContinuationRequest) {
                                $buttonClass .= ' continuation-request-button-active';
                            } elseif ($isLimitReached && !$isThreadOwner) {
                                $buttonClass .= ' continuation-request-button-disabled';
                            } else {
                                $buttonClass .= ' continuation-request-button-default';
                            }
                        @endphp
                        <button 
                            type="button" 
                            id="continuation-request-btn" 
                            onclick="toggleContinuationRequest({{ $thread->thread_id }})"
                            @if(!$canRequest) disabled @endif
                            class="{{ $buttonClass }}">
                            @if($isLimitReached && !isset($hasUserContinuationRequest) && !$isThreadOwner)
                                {{ \App\Services\LanguageService::trans('continuation_request_limit_reached', $lang) }}
                            @elseif(isset($hasUserContinuationRequest) && $hasUserContinuationRequest)
                                {{ \App\Services\LanguageService::trans('continuation_request_button_cancel', $lang) }}
                            @else
                                {{ \App\Services\LanguageService::trans('continuation_request_button', $lang) }}
                            @endif
                        </button>
                        <div class="continuation-request-info-container">
                            <span id="continuation-request-count" class="continuation-request-count">
                                @php
                                    $requestCount = isset($continuationRequestCount) ? $continuationRequestCount : 0;
                                    $countText = str_replace(':count', $requestCount, \App\Services\LanguageService::trans('continuation_request_count', $lang));
                                @endphp
                                {{ $countText }}
                                @if(isset($continuationRequestThreshold))
                                    / {{ $continuationRequestThreshold }}
                                @endif
                            </span>
                            <span id="thread-owner-request-status" class="continuation-request-status {{ isset($hasOwnerContinuationRequest) && $hasOwnerContinuationRequest ? 'continuation-request-status-requested' : 'continuation-request-status-not-requested' }}">
                                {{ isset($hasOwnerContinuationRequest) && $hasOwnerContinuationRequest 
                                    ? \App\Services\LanguageService::trans('thread_owner_requested', $lang)
                                    : \App\Services\LanguageService::trans('thread_owner_not_requested', $lang) }}
                            </span>
                        </div>
                    </div>
                </div>
                @endif
                @if(!empty($isThreadDeletedByReport))
                    <div class="thread-restriction-info">
                        <p>{{ \App\Services\LanguageService::trans('thread_deleted_info', $lang) }}</p>
                    </div>
                @else
                <!-- 返信先の表示エリア -->
                <div id="reply-target" class="reply-target">
                    <div class="reply-target-content">
                        <span class="reply-target-user"></span>
                        <span class="reply-target-body"></span>
                        <button type="button" class="reply-target-cancel" onclick="cancelReply()">×</button>
                    </div>
                </div>

                <form id="response-form" action="{{ route('responses.store', $thread) }}" method="POST" class="input-form" enctype="multipart/form-data">
                    @csrf
                    <input type="hidden" id="parent_response_id" name="parent_response_id" value="">
                    <div class="form-group response-form-group">
                        <div class="response-form-input-wrapper">
                            <label for="body" class="response-form-label-hidden">{{ \App\Services\LanguageService::trans('message_label', $lang) }}</label>
                            @if ($errors->has('body'))
                                @foreach ($errors->get('body') as $message)
                                    @php
                                        $insufficientCoinsMsg = \App\Services\LanguageService::trans('insufficient_coins', $lang);
                                        $isInsufficientCoins = $message === $insufficientCoinsMsg;
                                    @endphp
                                    @if ($isInsufficientCoins)
                                        <div class="alert alert-danger alert-danger-inline">
                                            {{ $message }}<br>
                                            @auth
                                            <button type="button" class="btn btn-primary watch-ad-button-small" onclick="watchAdFromThread()">
                                                {{ \App\Services\LanguageService::trans('watch_ad_to_earn_coins', $lang) }}
                                            </button>
                                            <span id="adWatchStatusThread" class="ad-status-thread"></span>
                                            @endauth
                                        </div>
                                    @else
                                        <div class="alert alert-danger alert-danger-inline">
                                            {{ $message }}
                                        </div>
                                    @endif
                                @endforeach
                            @endif
                            @error('media_file')
                                <div class="alert alert-danger alert-danger-inline">
                                    {{ \App\Services\LanguageService::trans('media_file_upload_failed', $lang) }}
                                </div>
                            @enderror
                            <textarea id="body" name="body" rows="2" placeholder="{{ \App\Services\LanguageService::trans('message_placeholder', $lang) }}">{{ old('body') }}</textarea>
                        </div>
                        <div class="media-file-container">
                            <input type="file" id="media_file" name="media_file" accept="image/jpeg,image/png,image/webp,video/mp4,video/webm,audio/mpeg,audio/mp4,audio/webm" class="media-file-input-hidden">
                            <button type="button" id="media-file-btn" class="media-file-btn" title="{{ \App\Services\LanguageService::trans('attach_file', $lang) }}">+</button>
                            <span id="media-file-name" class="media-file-name-display"></span>
                        </div>
                        <button type="submit" class="submit-btn">{{ \App\Services\LanguageService::trans('submit', $lang) }}</button>
                    </div>
                </form>

                @endif
            @else
                <div class="login-required-message">
                    <h3>{{ \App\Services\LanguageService::trans('login_required_for_comment', $lang) }}</h3>
                    <div class="auth-buttons">
                        <a href="{{ route('auth.choice') }}?intended={{ urlencode(route('threads.show', $thread)) }}" class="btn btn-primary">{{ \App\Services\LanguageService::trans('login_register', $lang) }}</a>
                    </div>
                </div>
            @endauth
            @endif
        </section>
    </div>

    @auth
    <!-- このスレッドページ専用の広告動画モーダル -->
    <div id="adVideoModalThread" class="ad-video-modal">
        <div class="ad-video-container">
            <button onclick="closeAdVideoFromThread()" class="ad-video-close-button">{{ \App\Services\LanguageService::trans('close_button', $lang) }}</button>
            <video id="adVideoThread" controls class="ad-video-player" preload="auto">
                <source src="{{ config('ads.test_ad_url') }}" type="video/mp4">
                @foreach(config('ads.test_ad_fallback_urls', []) as $fallbackUrl)
                <source src="{{ $fallbackUrl }}" type="video/mp4">
                @endforeach
                {{ \App\Services\LanguageService::trans('video_not_supported', $lang) }}
            </video>
        </div>
    </div>

    <!-- このスレッドページ専用のルーレット＆メッセージ -->
    <div id="coinRouletteOverlayThread" class="coin-roulette-overlay">
        <div class="coin-roulette-container">
            <div class="coin-roulette-title">{{ \App\Services\LanguageService::trans('coin_roulette', $lang) }}</div>
            <div id="coinRouletteValueThread" class="coin-roulette-value">-</div>
            <div id="coinRouletteMessageThread" class="coin-roulette-message"></div>
            <button id="coinRouletteSkipButtonThread" class="btn btn-secondary coin-roulette-skip-button">{{ \App\Services\LanguageService::trans('skip', $lang) }}</button>
            <button id="coinRouletteOkButtonThread" class="btn btn-primary coin-roulette-ok-button">OK</button>
        </div>
    </div>
    @endauth

    <meta name="thread-show-config" content="{{ json_encode([
        'threadId' => $thread->thread_id,
        'initialResponseCount' => count($thread->responses),
        'totalResponses' => $thread->responses()->count(),
        'responsesPerPage' => 10,
        'phpUploadMaxSize' => $phpUploadMaxSize ?? (2 * 1024 * 1024),
        'lang' => strtolower($lang),
        'isCurrentUserThreadOwner' => isset($isCurrentUserThreadOwner) && $isCurrentUserThreadOwner,
        'continuationRequestThreshold' => $continuationRequestThreshold ?? 3,
        'csrfToken' => csrf_token(),
        'routes' => [
            'storeRoute' => route('responses.store', $thread),
            'replyRoute' => route('responses.reply', ['thread' => $thread->thread_id, 'response' => ':responseId']),
            'watchAdRoute' => route('coins.watch-ad')
        ],
        'translations' => [
            'loading' => \App\Services\LanguageService::trans('loading', $lang),
            'searchMinLengthHint' => \App\Services\LanguageService::trans('search_min_length_hint', $lang),
            'searchHints' => \App\Services\LanguageService::trans('search_hints', $lang),
            'searchAndHint' => \App\Services\LanguageService::trans('search_and_hint', $lang),
            'searchExcludeHint' => \App\Services\LanguageService::trans('search_exclude_hint', $lang),
            'noSearchResults' => \App\Services\LanguageService::trans('no_search_results', $lang),
            'replyPlaceholderDetail' => \App\Services\LanguageService::trans('reply_placeholder_detail', $lang),
            'messagePlaceholder' => \App\Services\LanguageService::trans('message_placeholder', $lang),
            'fileFormatNotAllowed' => \App\Services\LanguageService::trans('file_format_not_allowed', $lang),
            'fileSizeTooLarge' => \App\Services\LanguageService::trans('file_size_too_large', $lang),
            'fileSizeExceedsPhpLimit' => \App\Services\LanguageService::trans('file_size_exceeds_php_limit', $lang),
            'imageFile' => \App\Services\LanguageService::trans('image_file', $lang),
            'videoFile' => \App\Services\LanguageService::trans('video_file', $lang),
            'audioFile' => \App\Services\LanguageService::trans('audio_file', $lang),
            'imageMaxSize' => \App\Services\LanguageService::trans('image_max_size', $lang),
            'videoMaxSize' => \App\Services\LanguageService::trans('video_max_size', $lang),
            'audioMaxSize' => \App\Services\LanguageService::trans('audio_max_size', $lang),
            'messageOrFileRequired' => \App\Services\LanguageService::trans('message_or_file_required', $lang),
            'adVideoLoading' => \App\Services\LanguageService::trans('ad_video_loading', $lang),
            'adVideoPlaying' => \App\Services\LanguageService::trans('ad_video_playing', $lang),
            'videoLoadFailed' => \App\Services\LanguageService::trans('video_load_failed', $lang),
            'videoLoadAborted' => \App\Services\LanguageService::trans('video_load_aborted', $lang),
            'videoNetworkError' => \App\Services\LanguageService::trans('video_network_error', $lang),
            'videoDecodeError' => \App\Services\LanguageService::trans('video_decode_error', $lang),
            'videoFormatNotSupported' => \App\Services\LanguageService::trans('video_format_not_supported', $lang),
            'videoUrlNotSet' => \App\Services\LanguageService::trans('video_url_not_set', $lang),
            'videoLoadError' => \App\Services\LanguageService::trans('video_load_error', $lang),
            'videoPlayFailed' => \App\Services\LanguageService::trans('video_play_failed', $lang),
            'errorOccurred' => \App\Services\LanguageService::trans('error_occurred', $lang),
            'continuationRequestButton' => \App\Services\LanguageService::trans('continuation_request_button', $lang),
            'continuationRequestButtonCancel' => \App\Services\LanguageService::trans('continuation_request_button_cancel', $lang),
            'continuationRequestLimitReached' => \App\Services\LanguageService::trans('continuation_request_limit_reached', $lang),
            'continuationRequestCount' => \App\Services\LanguageService::trans('continuation_request_count', $lang),
            'threadOwnerRequested' => \App\Services\LanguageService::trans('thread_owner_requested', $lang),
            'threadOwnerNotRequested' => \App\Services\LanguageService::trans('thread_owner_not_requested', $lang),
            'continuationThreadCreated' => \App\Services\LanguageService::trans('continuation_thread_created', $lang),
            'adWatchReward' => \App\Services\LanguageService::trans('ad_watch_reward', $lang),
            'responseLoadEmpty' => \App\Services\LanguageService::trans('responseLoadEmpty', $lang),
            'responseLoadFailed' => \App\Services\LanguageService::trans('responseLoadFailed', $lang),
            'searchError' => \App\Services\LanguageService::trans('searchError', $lang)
        ]
    ]) }}">
    <script src="{{ asset('js/thread-show.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    <script nonce="{{ $csp_nonce ?? '' }}">
        // metaタグから設定を読み込む
        (function() {
            const meta = document.querySelector('meta[name="thread-show-config"]');
            if (meta) {
                try {
                    window.threadShowConfig = JSON.parse(meta.getAttribute('content'));
                } catch (e) {
                    console.error('Failed to parse thread-show-config:', e);
                    window.threadShowConfig = {};
                }
            }
        })();
    </script>
@endsection 