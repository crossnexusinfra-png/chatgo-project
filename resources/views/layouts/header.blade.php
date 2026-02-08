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
                    <img src="{{ asset('images/logo.jpg') }}" alt="LOGO" class="logo-image">
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
            
            <script>
                window.headerConfig = {
                    translations: {
                        search: '{{ \App\Services\LanguageService::trans("search", $lang) }}'
                    }
                };
            </script>
            <script src="{{ asset('js/common-header.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
            @endif
            
            <div class="header-right">
                @auth
                    @php
                        $currentUser = auth()->user();
                        $currentCoins = $currentUser->coins ?? 0;
                    @endphp
                    <!-- ログイン時の表示 -->
                    <div class="header-coin-display">
                        🪙 {{ $currentCoins }} {{ \App\Services\LanguageService::trans('coins_unit', $lang) }}
                    </div>
                    <a href="{{ route('notifications.index') }}" class="header-button notification-btn" title="{{ \App\Services\LanguageService::trans('notifications', $lang) }}">
                        🔔
                        @if(isset($unreadNotificationCount) && $unreadNotificationCount > 0)
                            <span class="notification-badge">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                        @endif
                    </a>
                    <a href="{{ route('friends.index') }}" class="header-button" title="{{ \App\Services\LanguageService::trans('friends', $lang) }}">
                        🤝
                    </a>
                    <a href="{{ route('profile.index') }}" class="header-button profile-btn" title="{{ \App\Services\LanguageService::trans('profile', $lang) }}">
                        👤
                    </a>
                    <button class="header-button create-btn" id="openCreateThreadModal" title="{{ \App\Services\LanguageService::trans('create_thread', $lang) }}">
                        ✏️
                    </button>
                @else
                    <!-- 非ログイン時の表示 -->
                    <a href="{{ route('notifications.index') }}" class="header-button notification-btn" title="{{ \App\Services\LanguageService::trans('notifications', $lang) }}">
                        🔔
                        @if(isset($unreadNotificationCount) && $unreadNotificationCount > 0)
                            <span class="notification-badge">{{ $unreadNotificationCount > 99 ? '99+' : $unreadNotificationCount }}</span>
                        @endif
                    </a>
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
