@extends('layouts.app')

@php
    // コントローラーから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('my_page', $lang ?? \App\Services\LanguageService::getCurrentLanguage()) }}
@endsection

@push('styles')
    <link rel="stylesheet" href="{{ asset('css/profile.css') }}">
@endpush

@section('content')
<div class="profile-container">
    <div class="profile-header">
        <h1>{{ \App\Services\LanguageService::trans('my_page', $lang) }}</h1>
    </div>

    @if(session('success'))
        <div class="alert alert-success">
            {{ session('success') }}
        </div>
    @endif

    <div class="profile-content">
        <div class="profile-card">
            <div class="profile-image-section">
                @if($user->profile_image)
                    @php
                        // アバター画像（public/images/avatars/）の場合はasset()を使用
                        // それ以外（storage/）の場合はStorage::url()を使用（S3対応）
                        if (strpos($user->profile_image, 'avatars/') !== false || strpos($user->profile_image, 'images/avatars/') !== false) {
                            $imageUrl = asset($user->profile_image);
                        } else {
                            $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($user->profile_image);
                        }
                    @endphp
                    <img src="{{ $imageUrl }}" alt="{{ \App\Services\LanguageService::trans('profile_image_alt', $lang) }}" class="profile-image">
                @else
                    <div class="profile-image-placeholder">
                        <span>👤</span>
                    </div>
                @endif
            </div>

@php
    // $langが未定義の場合は取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    
    $displayUserName = $user->username;
    if ($user->user_identifier) {
        $displayUserName = $user->username . '@' . $user->user_identifier;
    }
    
    // 国コードから国旗の画像URLを取得する関数
    $getCountryFlagUrl = function($countryCode) {
        if (empty($countryCode) || $countryCode === 'OTHER') {
            return '';
        }
        $code = strtolower($countryCode);
        return "https://flagcdn.com/w20/{$code}.png";
    };
    
    // 国コードから国旗または「その他」マークを表示するHTMLを生成する関数
    $renderCountryFlag = function($countryCode) use ($getCountryFlagUrl, $lang) {
        if (empty($countryCode)) {
            return '';
        }
        if ($countryCode === 'OTHER') {
            return '<span class="country-flag-other" title="' . \App\Services\LanguageService::trans('country_other_title', $lang) . '">🌍</span>';
        }
        return '<img src="' . $getCountryFlagUrl($countryCode) . '" alt="' . htmlspecialchars($countryCode) . '" class="country-flag-img" onerror="this.style.display=\'none\'">';
    };
    
    // 国コードを取得
    $nationalityCode = $user->nationality ?? '';
    $residenceCode = $user->residence ?? '';
@endphp
            <div class="profile-info">
                <div class="profile-info-name-row">
                    <h2>{{ $displayUserName }}</h2>
                </div>

                <div class="profile-details">
                    <div class="detail-item">
                        <label>{{ \App\Services\LanguageService::trans('email_label', $lang) }}</label>
                        <span>{{ $user->email }}</span>
                    </div>
                    <div class="detail-item">
                        <label>{{ \App\Services\LanguageService::trans('phone_label', $lang) }}</label>
                        <span>{{ $user->phone }}</span>
                    </div>
                    <div class="detail-item">
                        <label>{{ \App\Services\LanguageService::trans('birthdate', $lang) }}</label>
                        <span>{{ $user->birthdate ? $user->birthdate->format('Y-m-d') : '-' }}</span>
                    </div>
                    <div class="detail-item">
                        <label>{{ \App\Services\LanguageService::trans('nationality_label', $lang) }}</label>
                        <span class="country-display">
                            @if($nationalityCode)
                                {!! $renderCountryFlag($nationalityCode) !!}
                            @endif
                            <span class="country-code-text">{{ $user->nationality_display ?? $nationalityCode }}</span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>{{ \App\Services\LanguageService::trans('residence_label', $lang) }}</label>
                        <span class="country-display">
                            <button type="button" class="btn-history" onclick="openResidenceHistoryModal({{ $user->user_id }})" title="{{ \App\Services\LanguageService::trans('residence_history_title', $lang) }}">
                                📝
                            </button>
                            @if($residenceCode)
                                {!! $renderCountryFlag($residenceCode) !!}
                            @endif
                            <span class="country-code-text">{{ $user->residence_display ?? $residenceCode }}</span>
                        </span>
                    </div>
                    <div class="detail-item">
                        <label>{{ \App\Services\LanguageService::trans('registration_date', $lang) }}</label>
                        <span data-utc-datetime="{{ $user->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $user->created_at->format('Y-m-d') }}</span>
                    </div>
                    @if($lastLoginAt)
                        <div class="detail-item">
                            <label>{{ \App\Services\LanguageService::trans('last_login', $lang) }}</label>
                            <span data-utc-datetime="{{ $lastLoginAt->format('Y-m-d H:i:s') }}" data-format="en">{{ $lastLoginAt->format('Y-m-d H:i') }}</span>
                        </div>
                    @endif
                    <div class="detail-item">
                        <label>{{ \App\Services\LanguageService::trans('consecutive_login_days_label', $lang) }}</label>
                        <span class="country-display">
                            <button type="button" class="btn-history" onclick="openCoinRewardModal()" title="{{ \App\Services\LanguageService::trans('view_coin_reward_table', $lang) }}">
                                📊
                            </button>
                            <span class="consecutive-days-badge">{{ \App\Services\LanguageService::trans('consecutive_login_days_count', $lang, ['days' => $consecutiveLoginDays]) }}</span>
                        </span>
                    </div>
                </div>

                <div class="profile-actions">
                    @if(($viewerAccountFrozen ?? false) === true)
                        <button type="button" class="btn btn-primary" disabled aria-disabled="true" title="{{ $viewerFrozenUiMessage ?? '' }}">
                            {{ \App\Services\LanguageService::trans('profile_edit', $lang) }}
                        </button>
                    @else
                        <a href="{{ route('profile.edit') }}" class="btn btn-primary">{{ \App\Services\LanguageService::trans('profile_edit', $lang) }}</a>
                    @endif
                    <form action="{{ route('logout') }}" method="POST" class="logout-form">
                        @csrf
                        <button type="submit" class="btn btn-danger" onclick="return confirm('{{ \App\Services\LanguageService::trans('logout_confirm', $lang) }}')">
                            {{ \App\Services\LanguageService::trans('logout', $lang) }}
                        </button>
                    </form>
                </div>
            </div>
        </div>

        <!-- お気に入りのルーム（作成したルームと同じカードデザイン） -->
        <div class="threads-section">
            <h2 class="threads-title">{{ \App\Services\LanguageService::trans('favorite_threads', $lang) }}</h2>
            @if(isset($favoriteThreads) && $favoriteThreads->count() > 0)
                <div class="threads-list">
                    @foreach($favoriteThreads as $thread)
                        @php
                            $restrictionInfo = $threadRestrictionData[$thread->thread_id] ?? ['isRestricted' => false, 'isDeletedByReport' => false];
                            $isRestricted = is_array($restrictionInfo) ? ($restrictionInfo['isRestricted'] ?? false) : $restrictionInfo;
                            $isDeletedByReport = is_array($restrictionInfo) ? ($restrictionInfo['isDeletedByReport'] ?? false) : false;
                            $imageReportData = $threadImageReportScoreData[$thread->thread_id] ?? ['score' => 0, 'isBlurred' => false, 'isDeletedByImageReport' => false];
                            $isImageBlurred = $imageReportData['isBlurred'] ?? false;
                            $isDeletedByImageReport = $imageReportData['isDeletedByImageReport'] ?? false;
                            $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
                            if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
                                $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
                            } else {
                                $threadImageUrl = $threadImage;
                            }
                            $r18Tags = [
                                '成人向けメディア・コンテンツ・創作',
                                '性体験談・性的嗜好・フェティシズム',
                                'アダルト業界・風俗・ナイトワーク'
                            ];
                            $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
                            $isResponseLimitReached = $thread->isResponseLimitReached();
                            $continuationNumber = $thread->getContinuationNumber();
                            $tag = $thread->tag ?? \App\Services\LanguageService::trans('other', $lang);
                            $translatedTag = \App\Services\LanguageService::transTag($tag, $lang);
                        @endphp
                        <div class="thread-card">
                            @if(!$isDeletedByImageReport)
                            <div class="thread-image-wrapper {{ $isImageBlurred ? 'image-reported' : '' }}">
                                <div class="thread-image-blur" data-bg-url="{{ e($threadImageUrl) }}"></div>
                                <img src="{{ $threadImageUrl }}" alt="{{ $thread->display_title ?? $thread->title }}">
                                @if($isImageBlurred)
                                    <div class="thread-image-reported-message">{{ \App\Services\LanguageService::trans('thread_image_reported', $lang) }}</div>
                                @endif
                                @if($continuationNumber !== null)
                                    <div class="continuation-badge-overlay" title="#{{ $continuationNumber }}">#{{ $continuationNumber }}</div>
                                @endif
                                @if($isResponseLimitReached)
                                    <div class="completed-mark" title="{{ \App\Services\LanguageService::trans('thread_completed', $lang) }}">{{ \App\Services\LanguageService::trans('thread_completed', $lang) }}</div>
                                @endif
                                @if($isR18Thread)
                                    <div class="r18-mark" title="{{ \App\Services\LanguageService::trans('r18_thread_mark', $lang) }}">🔞</div>
                                @endif
                                @if($isRestricted || $isDeletedByReport)
                                    <div class="restriction-flag" title="{{ \App\Services\LanguageService::trans('thread_restricted_title', $lang) }}">🚩</div>
                                @endif
                                @if($isDeletedByReport)
                                    <div class="deleted-by-report-mark" title="{{ \App\Services\LanguageService::trans('thread_deleted_by_report', $lang) }}">🗑️</div>
                                @endif
                            </div>
                            @else
                            <div class="thread-image-wrapper thread-image-deleted">
                                <span>{{ \App\Services\LanguageService::trans('thread_image_deleted', $lang) }}</span>
                            </div>
                            @endif
                            <div class="thread-content">
                                <div class="thread-header thread-header-flex">
                                    <a href="{{ route('threads.show', $thread) }}" class="thread-title-link thread-title-flex">
                                        {{ $thread->display_title ?? $thread->getCleanTitle() }}
                                    </a>
                                    @if($continuationNumber !== null)
                                        <span class="continuation-badge">
                                            #{{ $continuationNumber }}
                                        </span>
                                    @endif
                                </div>
                                <div class="thread-meta-info">
                                    <span class="meta-item">📅 <span data-utc-datetime="{{ $thread->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $thread->created_at->format('Y-m-d H:i') }}</span></span>
                                    <span class="meta-item">🏷️ {{ $translatedTag }}</span>
                                    <span class="meta-item">💬 {{ \App\Services\LanguageService::trans('response_count', $lang) }} {{ $thread->responses_count ?? 0 }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="no-threads">{{ \App\Services\LanguageService::trans('no_favorite_threads', $lang) }}</p>
            @endif
        </div>

        <!-- ユーザーが作成したルーム一覧（お気に入りの下に表示） -->
        <div class="threads-section">
            <h2 class="threads-title">{{ \App\Services\LanguageService::trans('my_threads', $lang) }}</h2>
            @if($threads->count() > 0)
                <div class="threads-list" id="threads-list">
                    @foreach($threads as $thread)
                        @php
                            $restrictionInfo = $threadRestrictionData[$thread->thread_id] ?? ['isRestricted' => false, 'isDeletedByReport' => false];
                            $isRestricted = is_array($restrictionInfo) ? ($restrictionInfo['isRestricted'] ?? false) : $restrictionInfo;
                            $isDeletedByReport = is_array($restrictionInfo) ? ($restrictionInfo['isDeletedByReport'] ?? false) : false;
                            $imageReportData = $threadImageReportScoreData[$thread->thread_id] ?? ['score' => 0, 'isBlurred' => false, 'isDeletedByImageReport' => false];
                            $isImageBlurred = $imageReportData['isBlurred'] ?? false;
                            $isDeletedByImageReport = $imageReportData['isDeletedByImageReport'] ?? false;
                            $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
                            // Storage::disk('public')->url()を使用してURLを取得（image_pathがstorageパスの場合、S3対応）
                            if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
                                $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
                            } else {
                                $threadImageUrl = $threadImage;
                            }
                            $r18Tags = [
                                '成人向けメディア・コンテンツ・創作',
                                '性体験談・性的嗜好・フェティシズム',
                                'アダルト業界・風俗・ナイトワーク'
                            ];
                            $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
                            $isResponseLimitReached = $thread->isResponseLimitReached();
                            $continuationNumber = $thread->getContinuationNumber();
                            $tag = $thread->tag ?? \App\Services\LanguageService::trans('other', $lang);
                            $translatedTag = \App\Services\LanguageService::transTag($tag, $lang);
                        @endphp
                        {{-- デバッグ用：条件を確認（本番環境では削除） --}}
                        @if(config('app.debug'))
                            <!-- DEBUG: Thread ID: {{ $thread->thread_id }}, isR18: {{ $isR18Thread ? 'true' : 'false' }}, isResponseLimitReached: {{ $isResponseLimitReached ? 'true' : 'false' }}, isRestricted: {{ $isRestricted ? 'true' : 'false' }}, isImageBlurred: {{ $isImageBlurred ? 'true' : 'false' }} -->
                        @endif
                        <div class="thread-card">
                            @if(!$isDeletedByImageReport)
                            <div class="thread-image-wrapper {{ $isImageBlurred ? 'image-reported' : '' }}">
                                <div class="thread-image-blur" data-bg-url="{{ e($threadImageUrl) }}"></div>
                                <img src="{{ $threadImageUrl }}" alt="{{ $thread->display_title ?? $thread->title }}">
                                @if($isImageBlurred)
                                    <div class="thread-image-reported-message">{{ \App\Services\LanguageService::trans('thread_image_reported', $lang) }}</div>
                                @endif
                                @if($continuationNumber !== null)
                                    <div class="continuation-badge-overlay" title="#{{ $continuationNumber }}">#{{ $continuationNumber }}</div>
                                @endif
                                @if($isResponseLimitReached)
                                    <div class="completed-mark" title="{{ \App\Services\LanguageService::trans('thread_completed', $lang) }}">{{ \App\Services\LanguageService::trans('thread_completed', $lang) }}</div>
                                @endif
                                @if($isR18Thread)
                                    <div class="r18-mark" title="{{ \App\Services\LanguageService::trans('r18_thread_mark', $lang) }}">🔞</div>
                                @endif
                                @if($isRestricted || $isDeletedByReport)
                                    <div class="restriction-flag" title="{{ \App\Services\LanguageService::trans('thread_restricted_title', $lang) }}">🚩</div>
                                @endif
                                @if($isDeletedByReport)
                                    <div class="deleted-by-report-mark" title="{{ \App\Services\LanguageService::trans('thread_deleted_by_report', $lang) }}">🗑️</div>
                                @endif
                            </div>
                            @else
                            <div class="thread-image-wrapper thread-image-deleted">
                                <span>{{ \App\Services\LanguageService::trans('thread_image_deleted', $lang) }}</span>
                            </div>
                            @endif
                            <div class="thread-content">
                                <div class="thread-header thread-header-flex">
                                    <a href="{{ route('threads.show', $thread) }}" class="thread-title-link thread-title-flex">
                                        {{ $thread->display_title ?? $thread->getCleanTitle() }}
                                    </a>
                                    @if($continuationNumber !== null)
                                        <span class="continuation-badge">
                                            #{{ $continuationNumber }}
                                        </span>
                                    @endif
                                </div>
                                <div class="thread-meta-info">
                                    <span class="meta-item">📅 <span data-utc-datetime="{{ $thread->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $thread->created_at->format('Y-m-d H:i') }}</span></span>
                                    <span class="meta-item">🏷️ {{ $translatedTag }}</span>
                                    <span class="meta-item">💬 {{ \App\Services\LanguageService::trans('response_count', $lang) }} {{ $thread->responses_count ?? 0 }}</span>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
                @if($totalCount > $threads->count())
                    <div class="load-more-container">
                        <button id="load-more-threads" class="btn-load-more" data-offset="{{ $threads->count() }}">
                            {{ \App\Services\LanguageService::trans('show_more', $lang) }}
                        </button>
                    </div>
                @endif
            @else
                <p class="no-threads">{{ \App\Services\LanguageService::trans('no_threads_created', $lang) }}</p>
            @endif
        </div>
    </div>
</div>

<!-- 居住地変更履歴モーダル -->
<div id="residenceHistoryModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>{{ \App\Services\LanguageService::trans('profile_residence_history_modal_title', $lang) }}</h2>
            <span class="close" onclick="closeResidenceHistoryModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div id="historyContent">
                <p class="loading">{{ \App\Services\LanguageService::trans('loading', $lang ?? \App\Services\LanguageService::getCurrentLanguage()) }}</p>
            </div>
        </div>
    </div>
</div>

<!-- コイン配布テーブルモーダル -->
<div id="coinRewardModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h2>{{ \App\Services\LanguageService::trans('coin_reward_modal_title', $lang) }}</h2>
            <span class="close" onclick="closeCoinRewardModal()">&times;</span>
        </div>
        <div class="modal-body">
            <div class="coin-reward-table">
                <table>
                    <thead>
                        <tr>
                            <th>{{ \App\Services\LanguageService::trans('coin_reward_day', $lang) }}</th>
                            <th>{{ \App\Services\LanguageService::trans('coin_reward_coins', $lang) }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($coinRewardTable as $reward)
                            <tr class="{{ isset($reward['isBonus']) && $reward['isBonus'] ? 'bonus-row' : '' }}">
                                <td>
                                    @if($reward['day'] == 1)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_1', $lang) }}
                                    @elseif($reward['day'] == 2)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_2', $lang) }}
                                    @elseif($reward['day'] == 3)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_3', $lang) }}
                                    @elseif($reward['day'] == 4)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_4', $lang) }}
                                    @elseif($reward['day'] == 5)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_5', $lang) }}
                                    @elseif($reward['day'] == 6)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_6', $lang) }}
                                    @elseif($reward['day'] == 7)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_7', $lang) }}
                                    @elseif($reward['day'] == 8)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_8_plus', $lang) }}
                                    @elseif($reward['day'] == 50)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_50', $lang) }}
                                    @elseif($reward['day'] == 100)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_100', $lang) }}
                                    @elseif($reward['day'] == 150)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_150', $lang) }}
                                    @elseif($reward['day'] == 200)
                                        {{ \App\Services\LanguageService::trans('coin_reward_day_200', $lang) }}
                                    @endif
                                </td>
                                <td>
                                    {{ $reward['coins'] }} {{ \App\Services\LanguageService::trans('coins_unit', $lang) }}
                                    @if($reward['day'] == 8)
                                        <span class="note-text">（{{ \App\Services\LanguageService::trans('coin_reward_day_8_plus_desc', $lang) }}）</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
                <p class="coin-reward-note">{{ \App\Services\LanguageService::trans('coin_reward_note', $lang) }}</p>
            </div>
        </div>
    </div>
</div>

    <script nonce="{{ $csp_nonce ?? '' }}">
        window.profileIndexConfig = {
            lang: '{{ $lang }}',
            userId: {{ $user->user_id }},
            translations: {
                loading: '{{ \App\Services\LanguageService::trans("profile_loading", $lang) }}',
                noHistory: '{{ \App\Services\LanguageService::trans("profile_no_history", $lang) }}',
                errorOccurred: '{{ \App\Services\LanguageService::trans("profile_error_occurred", $lang) }}',
                showMore: '{{ \App\Services\LanguageService::trans("show_more", $lang) }}'
            },
            countries: {
                'JP': '{{ \App\Services\LanguageService::trans("country_japan", $lang) }}',
                'US': '{{ \App\Services\LanguageService::trans("country_usa", $lang) }}',
                'GB': '{{ \App\Services\LanguageService::trans("country_uk", $lang) }}',
                'CA': '{{ \App\Services\LanguageService::trans("country_canada", $lang) }}',
                'AU': '{{ \App\Services\LanguageService::trans("country_australia", $lang) }}',
                'DE': '{{ \App\Services\LanguageService::trans("country_de", $lang) }}',
                'FR': '{{ \App\Services\LanguageService::trans("country_fr", $lang) }}',
                'NL': '{{ \App\Services\LanguageService::trans("country_nl", $lang) }}',
                'BE': '{{ \App\Services\LanguageService::trans("country_be", $lang) }}',
                'SE': '{{ \App\Services\LanguageService::trans("country_se", $lang) }}',
                'FI': '{{ \App\Services\LanguageService::trans("country_fi", $lang) }}',
                'DK': '{{ \App\Services\LanguageService::trans("country_dk", $lang) }}',
                'NO': '{{ \App\Services\LanguageService::trans("country_no", $lang) }}',
                'IS': '{{ \App\Services\LanguageService::trans("country_is", $lang) }}',
                'AT': '{{ \App\Services\LanguageService::trans("country_at", $lang) }}',
                'CH': '{{ \App\Services\LanguageService::trans("country_ch", $lang) }}',
                'IE': '{{ \App\Services\LanguageService::trans("country_ie", $lang) }}',
                'KR': '{{ \App\Services\LanguageService::trans("country_kr", $lang) }}',
                'SG': '{{ \App\Services\LanguageService::trans("country_sg", $lang) }}',
                'NZ': '{{ \App\Services\LanguageService::trans("country_nz", $lang) }}',
                'OTHER': '{{ \App\Services\LanguageService::trans("country_other", $lang) }}'
            }
        };
    </script>
    <script src="{{ asset('js/profile-index.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
@endsection
