<!-- ヘッダー -->
@php
    // $langが定義されていない場合は取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<header class="header">
    <div class="header-content">
        <div class="header-top">
            <div class="header-left">
                <!-- モバイルメニューボタン（threads.indexページのみ表示） -->
                @if(Route::currentRouteName() === 'threads.index')
                <button class="mobile-menu-btn" id="mobileMenuBtn" title="{{ \App\Services\LanguageService::trans('menu', $lang) }}">
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                    <span class="hamburger-line"></span>
                </button>
                @endif
                
                <a href="{{ route('threads.index') }}" class="logo">
                    <img src="{{ asset('images/logo.png') }}" alt="LOGO" class="logo-image">
                </a>
            </div>
            
            @if(!isset($hideSearch) || !$hideSearch)
            <div class="header-center">
                <form action="{{ route('threads.search') }}" method="GET" class="search-form" id="searchForm">
                    <div class="search-box">
                        <input type="text" 
                               name="q" 
                               placeholder="{{ \App\Services\LanguageService::trans('search_threads', $lang) }}" 
                               class="search-input"
                               value="{{ $searchQuery ?? '' }}"
                               id="searchInput"
                               required>
                        <button type="submit" class="search-button">{{ \App\Services\LanguageService::trans('search', $lang) }}</button>
                    </div>
                </form>
            </div>
            
            <div id="common-header-config" data-search="{{ \App\Services\LanguageService::trans('search', $lang) }}" hidden></div>
            <script src="{{ asset('js/common-header.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
            @endif
            
            <div class="header-right">
                @auth
                    @php
                        $currentUser = auth()->user();
                        $currentCoins = $currentUser->coins ?? 0;
                        $viewerIsAdminUser = !empty($currentUser->is_admin);
                    @endphp
                    <!-- ログイン時の表示 -->
                    <div class="header-coin-display">
                        @if($viewerIsAdminUser)
                            🪙 {{ \App\Services\LanguageService::trans('coins_unlimited_display', $lang) }} {{ \App\Services\LanguageService::trans('coins_unit', $lang) }}
                        @else
                            🪙 {{ $currentCoins }} {{ \App\Services\LanguageService::trans('coins_unit', $lang) }}
                        @endif
                    </div>
                    <a href="{{ route('notifications.index') }}" class="header-button notification-btn" title="{{ \App\Services\LanguageService::trans('notifications', $lang) }}">
                        🔔
                        @if(isset($unreadNotificationCount) && $unreadNotificationCount > 0)
                            <span class="notification-badge">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                        @endif
                    </a>
                    @if(!empty($viewerAccountFrozen))
                    <span class="header-button create-btn-disabled" role="button" aria-disabled="true" title="{{ !empty($viewerOnlyMandatoryNoticeRestriction) ? \App\Services\LanguageService::trans('mandatory_notice_no_friends_nav', $lang) : \App\Services\LanguageService::trans('account_frozen_no_friends_nav', $lang) }}">
                        🤝
                    </span>
                    @elseif(!empty($viewerIsAdminUser))
                    <span class="header-button create-btn-disabled" role="button" aria-disabled="true" title="{{ \App\Services\LanguageService::trans('admin_no_friend_nav', $lang) }}">
                        🤝
                    </span>
                    @else
                    <a href="{{ route('friends.index') }}" class="header-button" title="{{ \App\Services\LanguageService::trans('friends', $lang) }}">
                        🤝
                    </a>
                    @endif
                    <a href="{{ route('profile.index') }}" class="header-button profile-btn" title="{{ \App\Services\LanguageService::trans('profile', $lang) }}">
                        👤
                    </a>
                    <button type="button" class="header-button create-btn @if(!empty($viewerAccountFrozen)) create-btn-disabled @endif" id="openCreateThreadModal"
                        @if(!empty($viewerAccountFrozen)) disabled aria-disabled="true" @endif
                        title="{{ !empty($viewerAccountFrozen) ? (!empty($viewerOnlyMandatoryNoticeRestriction) ? \App\Services\LanguageService::trans('mandatory_notice_no_create_thread', $lang) : \App\Services\LanguageService::trans('account_frozen_no_create_thread', $lang)) : \App\Services\LanguageService::trans('create_thread', $lang) }}">
                        ✏️
                    </button>
                @else
                    <!-- 非ログイン時の表示（お知らせはログイン時のみ表示） -->
                    <a href="{{ route('auth.choice') }}" class="header-button auth-btn" title="{{ \App\Services\LanguageService::trans('login_register', $lang) }}">
                        🔑
                    </a>
                @endauth
            </div>
        </div>
    </div>
</header>

<!-- モバイルタグ一覧オーバーレイ（threads.indexページのみ表示） -->
@if(Route::currentRouteName() === 'threads.index')
<div class="mobile-tags-overlay" id="mobileTagsOverlay">
    <div class="overlay-header">
        <h3>{{ \App\Services\LanguageService::trans('tags', $lang) }}</h3>
        <button class="close-btn" id="closeTagsBtn">&times;</button>
    </div>
    <div class="overlay-content">
        @include('components.tag-list', ['lang' => $lang])
    </div>
</div>
@endif
