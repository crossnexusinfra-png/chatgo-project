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
    <div class="thread-card">
        @if(!$isDeletedByImageReport)
        <div class="thread-image-wrapper {{ $isImageBlurred ? 'image-reported' : '' }}" style="--thread-bg-image: url('{{ $threadImageUrl }}');">
            <img src="{{ $threadImageUrl }}" alt="{{ $thread->title }}">
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
                    {{ $thread->getCleanTitle() }}
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
