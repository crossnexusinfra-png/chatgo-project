@extends('layouts.app')

@php
    // コントローラーから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp

@section('title')
    {{ \App\Services\LanguageService::trans('site_title', $lang) }}
@endsection

@section('content')
<div class="main-container">
    <!-- 左サイドバー：タグ一覧 -->
    <aside class="sidebar">
        <h3>{{ \App\Services\LanguageService::trans('tags', $lang) }}</h3>
        @include('components.tag-list', ['lang' => $lang])
    </aside>

    <!-- メインコンテンツ -->
    <main class="main-content">
                <!-- 成功メッセージ表示 -->
                @if (session('success'))
                    <div class="alert alert-success">
                        {{ session('success') }}
                    </div>
                @endif

                <!-- バリデーションエラー表示 -->
                @if ($errors->any())
                    <div class="alert alert-danger">
                        <ul>
                            @foreach ($errors->all() as $error)
                                @php
                                    $insufficientCoinsMsg = \App\Services\LanguageService::trans('insufficient_coins', $lang);
                                    $isInsufficientCoins = $error === $insufficientCoinsMsg;
                                @endphp
                                @if ($isInsufficientCoins)
                                    <li>
                                        {{ $error }}
                                        @auth
                                        <div class="error-message-margin-top">
                                            <button id="watchAdBtnMainError" class="btn btn-primary watch-ad-button-error" onclick="watchAdFromIndex()">
                                                {{ \App\Services\LanguageService::trans('watch_ad_to_earn_coins', $lang) }}
                                            </button>
                                            <span id="adWatchStatusMain" class="ad-status-inline"></span>
                                        </div>
                                        @endauth
                                    </li>
                                @else
                                    <li>{{ $error }}</li>
                                @endif
                            @endforeach
                        </ul>
                    </div>
                @endif

                <!-- メインページのコンテンツ -->

                @auth
                <!-- 広告報酬ルーレット用オーバーレイ -->
                <div id="coinRouletteOverlay" class="coin-roulette-overlay">
                    <div class="coin-roulette-container">
                        <div class="coin-roulette-title">{{ \App\Services\LanguageService::trans('coin_roulette', $lang) }}</div>
                        <div id="coinRouletteValue" class="coin-roulette-value">-</div>
                        <div id="coinRouletteMessage" class="coin-roulette-message"></div>
                        <button id="coinRouletteSkipButton" class="btn btn-secondary coin-roulette-skip-button">{{ \App\Services\LanguageService::trans('skip', $lang) }}</button>
                        <button id="coinRouletteOkButton" class="btn btn-primary coin-roulette-ok-button">OK</button>
                    </div>
                </div>

                <!-- 広告動画視聴でコイン獲得（アクセス数の多いルームの上） -->
                <section class="post-list post-list-margin">
                    <div class="thread-category">
                        <h3 class="category-title">
                            <span class="category-icon">🎬</span>
                            {{ \App\Services\LanguageService::trans('coins_ad_video', $lang) }}
                        </h3>
                        <div class="thread-scroll-container thread-scroll-container-padding">
                            <p class="ad-section-description">
                                {{ \App\Services\LanguageService::trans('ad_video_description', $lang) }}
                            </p>
                            <button id="watchAdBtnMain" class="btn btn-primary watch-ad-button" onclick="watchAdFromIndex()">
                                {{ \App\Services\LanguageService::trans('watch_ad_to_earn_coins', $lang) }}
                            </button>
                            <div id="adWatchStatusMain" class="ad-status"></div>
                        </div>
                    </div>
                </section>
                @endauth

                <!-- カテゴリ別スレッド一覧 -->
                <section class="post-list">
                    @php
                        $categories = config('thread_categories.categories');
                        $displayConfig = config('thread_categories.display');
                        
                        // タグのカテゴリ情報を取得するヘルパー関数
                        function getTagCategory($tag, $lang = null) {
                            $tagCategories = [
                                '家事' => '生活・日常',
                                '育児' => '生活・日常',
                                '住まい・引越し' => '生活・日常',
                                '食事' => '生活・日常',
                                'レシピ' => '生活・日常',
                                'ショッピング' => '生活・日常',
                                '節約・エコ生活' => '生活・日常',
                                '病気・症状' => '健康・医療',
                                '健康管理' => '健康・医療',
                                'メンタルヘルス' => '健康・医療',
                                '医療制度・保険' => '健康・医療',
                                'ダイエット・運動' => '健康・医療',
                                '介護・福祉' => '健康・医療',
                                '就職・転職' => '仕事・キャリア',
                                '職場の悩み' => '仕事・キャリア',
                                'フリーランス・副業' => '仕事・キャリア',
                                'ビジネスマナー' => '仕事・キャリア',
                                '起業・経営' => '仕事・キャリア',
                                '学校・大学' => '学び・教育',
                                '資格・検定' => '学び・教育',
                                '語学学習' => '学び・教育',
                                '留学' => '学び・教育',
                                '子どもの教育' => '学び・教育',
                                '自己啓発' => '学び・教育',
                                'スマートフォン・アプリ' => 'テクノロジー・ガジェット',
                                'PC・周辺機器' => 'テクノロジー・ガジェット',
                                '家電・IoT機器' => 'テクノロジー・ガジェット',
                                '電子工作・DIY' => 'テクノロジー・ガジェット',
                                'ロボット・自動化機械' => 'テクノロジー・ガジェット',
                                'AI・機械学習' => 'テクノロジー・ガジェット',
                                'ソフトウェア・プログラミング' => 'テクノロジー・ガジェット',
                                'インターネット・SNS' => 'テクノロジー・ガジェット',
                                '音楽' => '趣味・エンタメ',
                                '映画・ドラマ' => '趣味・エンタメ',
                                'アニメ・漫画' => '趣味・エンタメ',
                                'ゲーム' => '趣味・エンタメ',
                                'スポーツ' => '趣味・エンタメ',
                                'アート・クラフト' => '趣味・エンタメ',
                                '旅行' => '旅行・地域',
                                '観光地情報' => '旅行・地域',
                                '地域の話題' => '旅行・地域',
                                '交通・移動手段' => '旅行・地域',
                                '恋愛相談' => '恋愛・人間関係',
                                '結婚・婚活' => '恋愛・人間関係',
                                '家族・親戚' => '恋愛・人間関係',
                                '友人関係' => '恋愛・人間関係',
                                '職場の人間関係' => '恋愛・人間関係',
                                'コミュニケーション' => '恋愛・人間関係',
                                '家計管理' => 'お金・投資',
                                '投資・資産運用' => 'お金・投資',
                                '保険・年金' => 'お金・投資',
                                '税金' => 'お金・投資',
                                'ローン・クレジット' => 'お金・投資',
                                '車選び・購入' => 'その他',
                                'バイク' => 'その他',
                                '整備・メンテナンス' => 'その他',
                                '運転・交通ルール' => 'その他',
                                'カスタマイズ' => 'その他',
                                '犬' => 'ペット・動物',
                                '猫' => 'ペット・動物',
                                '小動物' => 'ペット・動物',
                                '鳥類' => 'ペット・動物',
                                '爬虫類・両生類' => 'ペット・動物',
                                '魚類' => 'ペット・動物',
                                'ペットの健康・病気' => 'ペット・動物',
                                'ペット用品・フード' => 'ペット・動物',
                                'Q&A' => 'その他',
                                'その他' => 'その他',
                                '成人向けメディア・コンテンツ・創作' => 'R18・アダルト',
                                '性体験談・性的嗜好・フェティシズム' => 'R18・アダルト',
                                'アダルト業界・風俗・ナイトワーク' => 'R18・アダルト'
                            ];
                            
                            if ($lang === null) {
                                $lang = \App\Services\LanguageService::getCurrentLanguage();
                            }
                            
                            return $tagCategories[$tag] ?? \App\Services\LanguageService::trans('other', $lang);
                        }
                    @endphp

                    @foreach($categories as $categoryKey => $category)
                        @if($category['enabled'])
                            <div class="thread-category">
                                <h3 class="category-title">
                                    @php
                                        $categoryTitleKey = match($categoryKey) {
                                            'popular' => 'popular_threads',
                                            'trending' => 'trending_threads',
                                            'latest' => 'latest_threads',
                                            default => null,
                                        };
                                        $categoryTitle = $categoryTitleKey ? \App\Services\LanguageService::trans($categoryTitleKey, $lang) : $category['title'];
                                    @endphp
                                    @if(isset($category['icon']))
                                        <span class="category-icon">{{ $category['icon'] }}</span>
                                    @endif
                                    {{ $categoryTitle }}
                                </h3>
                                <div class="thread-scroll-container">
                                    <div class="thread-scroll-wrapper">
                                        @php
                                            // カテゴリに応じて適切な変数を使用
                                            if ($categoryKey === 'popular') {
                                                $categoryThreads = isset($popularThreads) && $popularThreads ? $popularThreads : collect();
                                                $totalCount = isset($popularTotalCount) ? $popularTotalCount : 0;
                                            } elseif ($categoryKey === 'trending') {
                                                $categoryThreads = isset($trendingThreads) && $trendingThreads ? $trendingThreads : collect();
                                                $totalCount = isset($trendingTotalCount) ? $trendingTotalCount : 0;
                                            } elseif ($categoryKey === 'latest') {
                                                $categoryThreads = isset($latestThreads) && $latestThreads ? $latestThreads : collect();
                                                $totalCount = isset($latestTotalCount) ? $latestTotalCount : 0;
                                            } else {
                                                $categoryThreads = collect();
                                                $totalCount = 0;
                                            }
                                        @endphp
                                        
                                        @forelse ($categoryThreads as $thread)
                                            @php
                                                $restrictionInfo = $threadRestrictionData[$thread->thread_id] ?? ['isRestricted' => false, 'isDeletedByReport' => false];
                                                $isRestricted = is_array($restrictionInfo) ? ($restrictionInfo['isRestricted'] ?? false) : $restrictionInfo;
                                                $isDeletedByReport = is_array($restrictionInfo) ? ($restrictionInfo['isDeletedByReport'] ?? false) : false;
                                                $imageReportData = $threadImageReportScoreData[$thread->thread_id] ?? ['score' => 0, 'isBlurred' => false, 'isDeletedByImageReport' => false];
                                                $isImageBlurred = $imageReportData['isBlurred'] ?? false;
                                                $isDeletedByImageReport = $imageReportData['isDeletedByImageReport'] ?? false;
                                            @endphp
                                            <article class="thread-item {{ $isRestricted ? 'restricted-thread' : '' }}">
                                                @php
                                                    $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
                                                    // Storage::disk('public')->url()を使用してURLを取得（image_pathがstorageパスの場合、S3対応）
                                                    if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
                                                        $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
                                                    } else {
                                                        $threadImageUrl = $threadImage;
                                                    }
                                                @endphp
                                                @if(!$isDeletedByImageReport)
                                                <div class="thread-image-wrapper {{ $isImageBlurred ? 'image-reported' : '' }}" style="--thread-bg-image: url('{{ $threadImageUrl }}');">
                                                    <img src="{{ $threadImageUrl }}" alt="{{ $thread->title }}">
                                                    @if($isImageBlurred)
                                                        <div class="thread-image-reported-message">{{ \App\Services\LanguageService::trans('thread_image_reported', $lang) }}</div>
                                                    @endif
                                                    @php
                                                        $r18Tags = [
                                                            '成人向けメディア・コンテンツ・創作',
                                                            '性体験談・性的嗜好・フェティシズム',
                                                            'アダルト業界・風俗・ナイトワーク'
                                                        ];
                                                        $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
                                                        $isResponseLimitReached = $thread->isResponseLimitReached();
                                                        $continuationNumber = $thread->getContinuationNumber();
                                                    @endphp
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
                                                <div class="thread-header thread-header-flex">
                                                    <a href="{{ route('threads.show', $thread) }}" class="thread-title thread-title-flex">
                                                        {{ $thread->getCleanTitle() }}
                                                    </a>
                                                    @php
                                                        $continuationNumber = $thread->getContinuationNumber();
                                                    @endphp
                                                    @if($continuationNumber !== null)
                                                    <span class="continuation-badge">
                                                        #{{ $continuationNumber }}
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="thread-meta">
                                                    @php
                                                        $tag = $thread->tag ?? \App\Services\LanguageService::trans('other', $lang);
                                                        $tagCategory = getTagCategory($tag, $lang);
                                                        $translatedTag = \App\Services\LanguageService::transTag($tag, $lang);
                                                        $translatedCategory = \App\Services\LanguageService::transTag($tagCategory, $lang);
                                                    @endphp
                                                    @php
                                                        $threadAuthorUser = $thread->user;
                                                        $threadAuthor = $threadAuthorUser ? $threadAuthorUser->username : '削除されたユーザー';
                                                        $threadAuthorDisplay = $threadAuthor;
                                                        if ($threadAuthorUser) {
                                                            // 長いユーザー名の場合は10文字に切り詰める
                                                            $trimmedAuthor = mb_strlen($threadAuthor) > 10 ? mb_substr($threadAuthor, 0, 10) . '…' : $threadAuthor;
                                                            $displayName = $trimmedAuthor;
                                                            $userId = $threadAuthorUser->user_identifier ?? $threadAuthorUser->user_id;
                                                            $threadAuthorDisplay = $displayName . '@' . $userId;
                                                        }
                                                    @endphp
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('author', $lang) }}: {{ $threadAuthorDisplay }}</div>
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('tag_label', $lang) }}: {{ $translatedCategory }} > {{ $translatedTag }}</div>
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('created_at_label', $lang) }}: @if($thread->created_at)<span data-utc-datetime="{{ $thread->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $thread->created_at->format('Y-m-d H:i') }}</span>@else{{ \App\Services\LanguageService::trans('unknown', $lang) }}@endif</div>
                                                </div>
                                            </article>
                                        @empty
                                            <div class="no-posts-placeholder">
                                                @php
                                                    $categoryTitleKey = match($categoryKey) {
                                                        'popular' => 'popular_threads',
                                                        'trending' => 'trending_threads',
                                                        'latest' => 'latest_threads',
                                                        default => null,
                                                    };
                                                    $categoryTitle = $categoryTitleKey ? \App\Services\LanguageService::trans($categoryTitleKey, $lang) : $category['title'];
                                                @endphp
                                                <p>{{ str_replace('{category}', $categoryTitle, \App\Services\LanguageService::trans('no_threads_yet', $lang)) }}</p>
                                            </div>
                                        @endforelse
                                        
                                        @if($totalCount > $categoryThreads->count())
                                            <div class="more-posts-link">
                                                @php
                                                @endphp
                                                <a href="{{ route($category['route'], $category['route_param']) }}" class="more-link">
                                                    <span class="more-text">{{ \App\Services\LanguageService::trans('show_more', $lang) }}</span>
                                                    <span class="more-arrow">{{ $displayConfig['show_more_link_icon'] }}</span>
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endforeach
                    
                    

                    {{-- お気に入りのルーム（ログイン時のみ） --}}
                    @auth
                        @if(isset($favoriteThreads) && $favoriteThreads->isNotEmpty())
                            <div class="thread-category">
                                <h3 class="category-title">
                                    @php
                                    @endphp
                                    <span class="category-icon">⭐</span>
                                    {{ \App\Services\LanguageService::trans('favorite_threads', $lang) }}
                                </h3>
                                <div class="thread-scroll-container">
                                    <div class="thread-scroll-wrapper">
                                        @foreach ($favoriteThreads as $thread)
                                            @php
                                                $restrictionInfo = $threadRestrictionData[$thread->thread_id] ?? ['isRestricted' => false, 'isDeletedByReport' => false];
                                                $isRestricted = is_array($restrictionInfo) ? ($restrictionInfo['isRestricted'] ?? false) : $restrictionInfo;
                                                $isDeletedByReport = is_array($restrictionInfo) ? ($restrictionInfo['isDeletedByReport'] ?? false) : false;
                                                $imageReportData = $threadImageReportScoreData[$thread->thread_id] ?? ['score' => 0, 'isBlurred' => false, 'isDeletedByImageReport' => false];
                                                $isImageBlurred = $imageReportData['isBlurred'] ?? false;
                                                $isDeletedByImageReport = $imageReportData['isDeletedByImageReport'] ?? false;
                                            @endphp
                                            <article class="thread-item {{ $isRestricted ? 'restricted-thread' : '' }}">
                                                @php
                                                    $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
                                                    // Storage::disk('public')->url()を使用してURLを取得（image_pathがstorageパスの場合、S3対応）
                                                    if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
                                                        $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
                                                    } else {
                                                        $threadImageUrl = $threadImage;
                                                    }
                                                @endphp
                                                @if(!$isDeletedByImageReport)
                                                <div class="thread-image-wrapper {{ $isImageBlurred ? 'image-reported' : '' }}" style="--thread-bg-image: url('{{ $threadImageUrl }}');">
                                                    <img src="{{ $threadImageUrl }}" alt="{{ $thread->title }}">
                                                    @if($isImageBlurred)
                                                        <div class="thread-image-reported-message">{{ \App\Services\LanguageService::trans('thread_image_reported', $lang) }}</div>
                                                    @endif
                                                    @php
                                                        $r18Tags = [
                                                            '成人向けメディア・コンテンツ・創作',
                                                            '性体験談・性的嗜好・フェティシズム',
                                                            'アダルト業界・風俗・ナイトワーク'
                                                        ];
                                                        $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
                                                        $isResponseLimitReached = $thread->isResponseLimitReached();
                                                        $continuationNumber = $thread->getContinuationNumber();
                                                    @endphp
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
                                                <div class="thread-header thread-header-flex">
                                                    <a href="{{ route('threads.show', $thread) }}" class="thread-title thread-title-flex">
                                                        {{ $thread->getCleanTitle() }}
                                                    </a>
                                                    @php
                                                        $continuationNumber = $thread->getContinuationNumber();
                                                    @endphp
                                                    @if($continuationNumber !== null)
                                                    <span class="continuation-badge">
                                                        #{{ $continuationNumber }}
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="thread-meta">
                                                    @php
                                                        $tag = $thread->tag ?? \App\Services\LanguageService::trans('other', $lang);
                                                        $tagCategory = getTagCategory($tag, $lang);
                                                        $translatedTag = \App\Services\LanguageService::transTag($tag, $lang);
                                                        $translatedCategory = \App\Services\LanguageService::transTag($tagCategory, $lang);
                                                    @endphp
                                                    @php
                                                        $threadAuthorUser = $thread->user;
                                                        $threadAuthor = $threadAuthorUser ? $threadAuthorUser->username : '削除されたユーザー';
                                                        $threadAuthorDisplay = $threadAuthor;
                                                        if ($threadAuthorUser) {
                                                            // 長いユーザー名の場合は10文字に切り詰める
                                                            $trimmedAuthor = mb_strlen($threadAuthor) > 10 ? mb_substr($threadAuthor, 0, 10) . '…' : $threadAuthor;
                                                            $displayName = $trimmedAuthor;
                                                            $userId = $threadAuthorUser->user_identifier ?? $threadAuthorUser->user_id;
                                                            $threadAuthorDisplay = $displayName . '@' . $userId;
                                                        }
                                                    @endphp
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('author', $lang) }}: {{ $threadAuthorDisplay }}</div>
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('tag_label', $lang) }}: {{ $translatedCategory }} > {{ $translatedTag }}</div>
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('created_at_label', $lang) }}: @if($thread->created_at)<span data-utc-datetime="{{ $thread->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $thread->created_at->format('Y-m-d H:i') }}</span>@else{{ \App\Services\LanguageService::trans('unknown', $lang) }}@endif</div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endauth

                    {{-- 最近アクセスしたルーム（ログイン時のみ） --}}
                    @auth
                        @if(isset($recentAccessThreads) && $recentAccessThreads->isNotEmpty())
                            <div class="thread-category">
                                <h3 class="category-title">
                                    @php
                                    @endphp
                                    <span class="category-icon">🕒</span>
                                    {{ \App\Services\LanguageService::trans('recent_access_threads', $lang) }}
                                </h3>
                                <div class="thread-scroll-container">
                                    <div class="thread-scroll-wrapper">
                                        @foreach ($recentAccessThreads as $thread)
                                            @php
                                                $restrictionInfo = $threadRestrictionData[$thread->thread_id] ?? ['isRestricted' => false, 'isDeletedByReport' => false];
                                                $isRestricted = is_array($restrictionInfo) ? ($restrictionInfo['isRestricted'] ?? false) : $restrictionInfo;
                                                $isDeletedByReport = is_array($restrictionInfo) ? ($restrictionInfo['isDeletedByReport'] ?? false) : false;
                                                $imageReportData = $threadImageReportScoreData[$thread->thread_id] ?? ['score' => 0, 'isBlurred' => false, 'isDeletedByImageReport' => false];
                                                $isImageBlurred = $imageReportData['isBlurred'] ?? false;
                                                $isDeletedByImageReport = $imageReportData['isDeletedByImageReport'] ?? false;
                                            @endphp
                                            <article class="thread-item {{ $isRestricted ? 'restricted-thread' : '' }}">
                                                @php
                                                    $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
                                                    // Storage::disk('public')->url()を使用してURLを取得（image_pathがstorageパスの場合、S3対応）
                                                    if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
                                                        $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
                                                    } else {
                                                        $threadImageUrl = $threadImage;
                                                    }
                                                @endphp
                                                @if(!$isDeletedByImageReport)
                                                <div class="thread-image-wrapper {{ $isImageBlurred ? 'image-reported' : '' }}" style="--thread-bg-image: url('{{ $threadImageUrl }}');">
                                                    <img src="{{ $threadImageUrl }}" alt="{{ $thread->title }}">
                                                    @if($isImageBlurred)
                                                        <div class="thread-image-reported-message">{{ \App\Services\LanguageService::trans('thread_image_reported', $lang) }}</div>
                                                    @endif
                                                    @php
                                                        $r18Tags = [
                                                            '成人向けメディア・コンテンツ・創作',
                                                            '性体験談・性的嗜好・フェティシズム',
                                                            'アダルト業界・風俗・ナイトワーク'
                                                        ];
                                                        $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
                                                        $isResponseLimitReached = $thread->isResponseLimitReached();
                                                        $continuationNumber = $thread->getContinuationNumber();
                                                    @endphp
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
                                                <div class="thread-header thread-header-flex">
                                                    <a href="{{ route('threads.show', $thread) }}" class="thread-title thread-title-flex">
                                                        {{ $thread->getCleanTitle() }}
                                                    </a>
                                                    @php
                                                        $continuationNumber = $thread->getContinuationNumber();
                                                    @endphp
                                                    @if($continuationNumber !== null)
                                                    <span class="continuation-badge">
                                                        #{{ $continuationNumber }}
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="thread-meta">
                                                    @php
                                                        $tag = $thread->tag ?? \App\Services\LanguageService::trans('other', $lang);
                                                        $tagCategory = getTagCategory($tag, $lang);
                                                        $translatedTag = \App\Services\LanguageService::transTag($tag, $lang);
                                                        $translatedCategory = \App\Services\LanguageService::transTag($tagCategory, $lang);
                                                    @endphp
                                                    @php
                                                        $threadAuthorUser = $thread->user;
                                                        $threadAuthor = $threadAuthorUser ? $threadAuthorUser->username : '削除されたユーザー';
                                                        $threadAuthorDisplay = $threadAuthor;
                                                        if ($threadAuthorUser) {
                                                            // 長いユーザー名の場合は10文字に切り詰める
                                                            $trimmedAuthor = mb_strlen($threadAuthor) > 10 ? mb_substr($threadAuthor, 0, 10) . '…' : $threadAuthor;
                                                            $displayName = $trimmedAuthor;
                                                            $userId = $threadAuthorUser->user_identifier ?? $threadAuthorUser->user_id;
                                                            $threadAuthorDisplay = $displayName . '@' . $userId;
                                                        }
                                                    @endphp
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('author', $lang) }}: {{ $threadAuthorDisplay }}</div>
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('tag_label', $lang) }}: {{ $translatedCategory }} > {{ $translatedTag }}</div>
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('created_at_label', $lang) }}: @if($thread->created_at)<span data-utc-datetime="{{ $thread->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $thread->created_at->format('Y-m-d H:i') }}</span>@else{{ \App\Services\LanguageService::trans('unknown', $lang) }}@endif</div>
                                                </div>
                                            </article>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    @endauth

                    {{-- 閲覧履歴からのタグセクション --}}
                    @if(isset($tagThreads) && !empty($tagThreads))
                        @foreach($tagThreads as $tagName => $tagData)
                            @php
                                $threads = $tagData['threads'] ?? collect();
                                $totalCount = $tagData['total_count'] ?? 0;
                            @endphp
                            <div class="thread-category">
                                <h3 class="category-title">
                                    @php
                                        $translatedTagName = \App\Services\LanguageService::transTag($tagName, $lang);
                                    @endphp
                                    <span class="category-icon">🏷️</span>
                                    {{ $translatedTagName }}
                                </h3>
                                <div class="thread-scroll-container">
                                    <div class="thread-scroll-wrapper">
                                        @forelse ($threads as $thread)
                                            @php
                                                $restrictionInfo = $threadRestrictionData[$thread->thread_id] ?? ['isRestricted' => false, 'isDeletedByReport' => false];
                                                $isRestricted = is_array($restrictionInfo) ? ($restrictionInfo['isRestricted'] ?? false) : $restrictionInfo;
                                                $isDeletedByReport = is_array($restrictionInfo) ? ($restrictionInfo['isDeletedByReport'] ?? false) : false;
                                                $imageReportData = $threadImageReportScoreData[$thread->thread_id] ?? ['score' => 0, 'isBlurred' => false, 'isDeletedByImageReport' => false];
                                                $isImageBlurred = $imageReportData['isBlurred'] ?? false;
                                                $isDeletedByImageReport = $imageReportData['isDeletedByImageReport'] ?? false;
                                            @endphp
                                            <article class="thread-item {{ $isRestricted ? 'restricted-thread' : '' }}">
                                                @php
                                                    $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
                                                    // Storage::disk('public')->url()を使用してURLを取得（image_pathがstorageパスの場合、S3対応）
                                                    if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
                                                        $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
                                                    } else {
                                                        $threadImageUrl = $threadImage;
                                                    }
                                                @endphp
                                                @if(!$isDeletedByImageReport)
                                                <div class="thread-image-wrapper {{ $isImageBlurred ? 'image-reported' : '' }}" style="--thread-bg-image: url('{{ $threadImageUrl }}');">
                                                    <img src="{{ $threadImageUrl }}" alt="{{ $thread->title }}">
                                                    @if($isImageBlurred)
                                                        <div class="thread-image-reported-message">{{ \App\Services\LanguageService::trans('thread_image_reported', $lang) }}</div>
                                                    @endif
                                                    @php
                                                        $r18Tags = [
                                                            '成人向けメディア・コンテンツ・創作',
                                                            '性体験談・性的嗜好・フェティシズム',
                                                            'アダルト業界・風俗・ナイトワーク'
                                                        ];
                                                        $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
                                                        $isResponseLimitReached = $thread->isResponseLimitReached();
                                                        $continuationNumber = $thread->getContinuationNumber();
                                                    @endphp
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
                                                <div class="thread-header thread-header-flex">
                                                    <a href="{{ route('threads.show', $thread) }}" class="thread-title thread-title-flex">
                                                        {{ $thread->getCleanTitle() }}
                                                    </a>
                                                    @php
                                                        $continuationNumber = $thread->getContinuationNumber();
                                                    @endphp
                                                    @if($continuationNumber !== null)
                                                    <span class="continuation-badge">
                                                        #{{ $continuationNumber }}
                                                    </span>
                                                    @endif
                                                </div>
                                                <div class="thread-meta">
                                                    @php
                                                        $tag = $thread->tag ?? \App\Services\LanguageService::trans('other', $lang);
                                                        $tagCategory = getTagCategory($tag, $lang);
                                                        $translatedTag = \App\Services\LanguageService::transTag($tag, $lang);
                                                        $translatedCategory = \App\Services\LanguageService::transTag($tagCategory, $lang);
                                                    @endphp
                                                    @php
                                                        $threadAuthorUser = $thread->user;
                                                        $threadAuthor = $threadAuthorUser ? $threadAuthorUser->username : '削除されたユーザー';
                                                        $threadAuthorDisplay = $threadAuthor;
                                                        if ($threadAuthorUser) {
                                                            // 長いユーザー名の場合は10文字に切り詰める
                                                            $trimmedAuthor = mb_strlen($threadAuthor) > 10 ? mb_substr($threadAuthor, 0, 10) . '…' : $threadAuthor;
                                                            $displayName = $trimmedAuthor;
                                                            $userId = $threadAuthorUser->user_identifier ?? $threadAuthorUser->user_id;
                                                            $threadAuthorDisplay = $displayName . '@' . $userId;
                                                        }
                                                    @endphp
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('author', $lang) }}: {{ $threadAuthorDisplay }}</div>
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('tag_label', $lang) }}: {{ $translatedCategory }} > {{ $translatedTag }}</div>
                                                    <div class="meta-item">{{ \App\Services\LanguageService::trans('created_at_label', $lang) }}: @if($thread->created_at)<span data-utc-datetime="{{ $thread->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $thread->created_at->format('Y-m-d H:i') }}</span>@else{{ \App\Services\LanguageService::trans('unknown', $lang) }}@endif</div>
                                                </div>
                                            </article>
                                        @empty
                                            <div class="no-posts-placeholder">
                                                @php
                                                    // $tagNameは外側の@foreachループで定義されているので、確実に使えるように再取得
                                                    $currentTagName = $tagName ?? '';
                                                    $translatedTagName = \App\Services\LanguageService::transTag($currentTagName, $lang);
                                                @endphp
                                                <p>{{ str_replace('{category}', $translatedTagName . \App\Services\LanguageService::trans('related', $lang), \App\Services\LanguageService::trans('no_threads_yet', $lang)) }}</p>
                                            </div>
                                        @endforelse
                                        
                                        @if($totalCount > $threads->count())
                                            <div class="more-posts-link">
                                                @php
                                                @endphp
                                                <a href="{{ route('threads.tag', $tagName) }}" class="more-link">
                                                    <span class="more-text">{{ \App\Services\LanguageService::trans('show_more', $lang) }}</span>
                                                    <span class="more-arrow">{{ $displayConfig['show_more_link_icon'] }}</span>
                                                </a>
                                            </div>
                                        @endif
                                    </div>
                                </div>
                            </div>
                        @endforeach
                    @endif
                </section>
                <section class="suggestion-section">
                    <h3 class="suggestion-title">{{ \App\Services\LanguageService::trans('suggestion_title', $lang) }}</h3>
                    <p class="suggestion-description">{{ \App\Services\LanguageService::trans('suggestion_description', $lang) }}</p>
                    <form method="POST" action="{{ route('suggestions.store') }}">
                        @csrf
                        <textarea name="message" rows="4" placeholder="{{ \App\Services\LanguageService::trans('suggestion_placeholder', $lang) }}" class="suggestion-textarea" required>{{ old('message') }}</textarea>
                        <div class="suggestion-submit-container">
                            <button type="submit" class="btn suggestion-submit-button">{{ \App\Services\LanguageService::trans('suggestion_submit', $lang) }}</button>
                        </div>
                    </form>
                </section>
            </main>
        </div>

@auth
    <meta name="thread-index-config" content="{{ json_encode([
        'csrfToken' => csrf_token(),
        'routes' => [
            'watchAdRoute' => route('coins.watch-ad')
        ],
        'adUrls' => [
            'mainUrl' => config('ads.test_ad_url'),
            'fallbackUrls' => config('ads.test_ad_fallback_urls', [])
        ],
        'translations' => [
            'closeButton' => \App\Services\LanguageService::trans('close_button', $lang),
            'adVideoLoading' => \App\Services\LanguageService::trans('ad_video_loading', $lang),
            'adVideoPlaying' => \App\Services\LanguageService::trans('ad_video_playing', $lang),
            'videoPlayerInitFailed' => \App\Services\LanguageService::trans('video_player_init_failed', $lang),
            'videoPlayFailed' => \App\Services\LanguageService::trans('video_play_failed', $lang),
            'errorOccurred' => \App\Services\LanguageService::trans('error_occurred', $lang),
            'videoLoadError' => \App\Services\LanguageService::trans('video_load_error', $lang),
            'videoNotSupported' => \App\Services\LanguageService::trans('video_not_supported', $lang),
            'adWatchReward' => \App\Services\LanguageService::trans('ad_watch_reward', $lang)
        ]
    ]) }}">
    <script src="{{ asset('js/thread-index.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
    <script nonce="{{ $csp_nonce ?? '' }}">
        // metaタグから設定を読み込む
        (function() {
            const meta = document.querySelector('meta[name="thread-index-config"]');
            if (meta) {
                try {
                    window.threadIndexConfig = JSON.parse(meta.getAttribute('content'));
                } catch (e) {
                    console.error('Failed to parse thread-index-config:', e);
                    window.threadIndexConfig = {};
                }
            }
        })();
    </script>
@endauth

@endsection