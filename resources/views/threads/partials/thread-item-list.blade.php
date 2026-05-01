@php
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
@php
    $listRowOffset = $listRowOffset ?? 0;
    $adSlotEvery = (int) config('adsense.inline_banner_every_n', 4);
@endphp
@foreach ($threads as $thread)
    @php
        $restrictionInfo = $threadRestrictionData[$thread->thread_id] ?? ['isRestricted' => false, 'isDeletedByReport' => false];
        $isRestricted = is_array($restrictionInfo) ? ($restrictionInfo['isRestricted'] ?? false) : $restrictionInfo;
        $isDeletedByReport = is_array($restrictionInfo) ? ($restrictionInfo['isDeletedByReport'] ?? false) : false;
        $imageReportData = $threadImageReportScoreData[$thread->thread_id] ?? ['score' => 0, 'isBlurred' => false, 'isDeletedByImageReport' => false];
        $isImageBlurred = $imageReportData['isBlurred'] ?? false;
        $isDeletedByImageReport = $imageReportData['isDeletedByImageReport'] ?? false;
    @endphp
    <article class="post-item {{ $isRestricted ? 'restricted-thread' : '' }}">
        @php
            $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
            if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
                $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
            } else {
                $threadImageUrl = $threadImage;
            }
        @endphp
        @if(!$isDeletedByImageReport)
        <div class="thread-image-wrapper {{ $isImageBlurred ? 'image-reported' : '' }}">
            <div class="thread-image-blur" data-bg-url="{{ e($threadImageUrl) }}"></div>
            <img src="{{ $threadImageUrl }}" alt="{{ $thread->display_title ?? $thread->title }}">
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
        <div class="post-header thread-header-flex">
            <a href="{{ route('threads.show', $thread) }}" class="post-title thread-title-flex">
                {{ $thread->display_title ?? $thread->getCleanTitle() }}
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
                $threadAuthor = $threadAuthorUser ? $threadAuthorUser->username : \App\Services\LanguageService::trans('deleted_user', $lang);
                $threadAuthorDisplay = $threadAuthor;
                if ($threadAuthorUser) {
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
    @php $rowIdx = $listRowOffset + $loop->iteration; @endphp
    @if($rowIdx >= $adSlotEvery && $rowIdx % $adSlotEvery === 0)
    <div class="posts-grid-ad-row">
        @include('components.adsense-inline-banner', ['instanceId' => 'more-'.$rowIdx])
    </div>
    @endif
@endforeach
