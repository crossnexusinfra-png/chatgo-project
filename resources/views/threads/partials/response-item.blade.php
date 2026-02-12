    @php
    // 自分のリプライかどうかを判定
    $responseUser = $users->get($response->user_id);
    $isMyResponse = $currentUser && $responseUser && $currentUser->user_id === $response->user_id;
    
    // ユーザー名を取得（削除されたユーザーの場合は「削除されたユーザー」を表示）
    $username = $responseUser ? $responseUser->username : '削除されたユーザー';
    
    // 長過ぎるユーザー名は10文字でトリムして表示
    $baseName = mb_strlen($username) > 10
        ? mb_substr($username, 0, 10) . '…'
        : $username;
    
    // ユーザー名表示用の文字列を生成（ユーザー名@ユーザーID）
    // user_identifier がなければ user_id をフォールバック表示
    $displayUserName = $baseName;
    if ($responseUser) {
        $displayUserName = $baseName . '@' . ($responseUser->user_identifier ?? $responseUser->user_id);
    }
    
    // リプライが非表示にすべきかを判定（コントローラーから渡されたデータを使用）
    $restrictionData = $responseRestrictionData[$response->response_id] ?? [
        'shouldBeHidden' => false,
        'isDeletedByReport' => false,
        'restrictionReasons' => []
    ];
    $shouldBeHidden = $restrictionData['shouldBeHidden'];
    $isDeletedByReport = $restrictionData['isDeletedByReport'];
    $isAcknowledged = session('acknowledged_response_' . $response->response_id);
    $restrictionReasons = $restrictionData['restrictionReasons'];
    
    // 言語設定を取得（コントローラーから渡された$langを使用、なければ取得）
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    
    // 国コードから国旗の画像URLを取得する関数
    $getCountryFlagUrl = function($countryCode) {
        // 国コードを小文字に変換（CDNのURL形式に合わせる）
        $code = strtolower($countryCode);
        // flagcdn.com を使用して国旗画像を取得
        return "https://flagcdn.com/w20/{$code}.png";
    };
    
    // 国コードから国旗または「その他」マークを表示するHTMLを生成する関数
    $renderCountryFlag = function($countryCode) use ($getCountryFlagUrl) {
        if (empty($countryCode)) {
            return '';
        }
        if ($countryCode === 'OTHER') {
            // 「その他」の場合は地球アイコンを表示
            return '<span class="country-flag-other" title="その他">🌍</span>';
        }
        // 通常の国旗を表示
        return '<img src="' . $getCountryFlagUrl($countryCode) . '" alt="' . htmlspecialchars($countryCode) . '" class="country-flag-img" onerror="this.style.display=\'none\'">';
    };
    
    // ユーザーの出身国と居住国の国旗を取得
    // 国コードを直接使用（SMSで登録可能な全ての国の国旗が表示される）
    $nationalityCode = '';
    $residenceCode = '';
    if ($responseUser) {
        $nationalityCode = $responseUser->nationality ?? '';
        $residenceCode = $responseUser->residence ?? '';
        // 空の場合は非表示、OTHERの場合はそのまま（地球アイコンを表示）
        if (empty($nationalityCode)) {
            $nationalityCode = '';
        }
        if (empty($residenceCode)) {
            $residenceCode = '';
        }
    }
@endphp

@if($isDeletedByReport)
    <!-- 管理承認により削除扱い -->
    <article class="response-item" data-response-id="{{ $response->response_id }}">
        <div class="response-body response-deleted-text">{{ \App\Services\LanguageService::trans('response_deleted_by_report', $lang) }}</div>
        <div class="response-time" data-utc-datetime="{{ $response->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $response->created_at->format('Y-m-d H:i') }}</div>
    </article>
@elseif($shouldBeHidden && !$isAcknowledged)
    <!-- リプライ制限警告 -->
    <article class="response-item restriction-warning">
        <div class="response-restriction-warning-content">
            <h4 class="response-restriction-warning-title">{{ \App\Services\LanguageService::trans('response_restricted_warning', $lang) }}</h4>
            <p class="response-restriction-warning-text">
                {{ \App\Services\LanguageService::trans('response_restricted_description', $lang) }}
            </p>
            <ul class="response-restriction-reasons-list">
                @if(!empty($restrictionReasons))
                    @foreach($restrictionReasons as $reason)
                        <li>{{ $reason }}</li>
                    @endforeach
                @else
                    <li>{{ \App\Services\LanguageService::trans('response_restricted_by_report', $lang) }}</li>
                @endif
            </ul>
            <form action="{{ route('responses.acknowledge', ['thread' => $thread->thread_id, 'response' => $response->response_id]) }}" method="POST" class="response-restriction-form">
                @csrf
                <button type="submit" class="response-restriction-acknowledge-btn">
                    {{ \App\Services\LanguageService::trans('acknowledge_response_restriction', $lang) }}
                </button>
            </form>
        </div>
    </article>
@else
<article class="response-item {{ $isMyResponse ? 'my-response' : '' }} {{ $shouldBeHidden ? 'reported-response' : '' }}" data-search-text="{{ strtolower($response->body) }}" data-user="{{ strtolower($username) }}" data-response-id="{{ $response->response_id }}">
    <!-- 返信元のリプライ簡略表示 -->
    @if($response->parentResponse)
        @php
            $parentResponseUser = $users->get($response->parentResponse->user_id);
            $parentUsername = $parentResponseUser ? $parentResponseUser->username : '削除されたユーザー';
            $parentBaseName = mb_strlen($parentUsername) > 10
                ? mb_substr($parentUsername, 0, 10) . '…'
                : $parentUsername;
            $parentDisplayUserName = $parentBaseName;
            if ($parentResponseUser) {
                $parentDisplayUserName = $parentBaseName . '@' . ($parentResponseUser->user_identifier ?? $parentResponseUser->user_id);
            }
        @endphp
        <div class="reply-source" onclick="scrollToResponse({{ $response->parentResponse->response_id }})">
            <span class="reply-source-user">{{ $parentDisplayUserName }}</span>
            <span class="reply-source-body">{!! linkify_urls($response->parentResponse->display_body ?? $response->parentResponse->body) !!}</span>
        </div>
    @endif

    @if($shouldBeHidden)
        <div class="response-reported-indicator" title="{{ \App\Services\LanguageService::trans('response_reported_indicator', $lang) }}">🚩</div>
    @endif

    <div class="response-meta">
        @if($responseUser)
            @if($isMyResponse)
                {{-- 自分のリプライの場合はリンクなしで表示 --}}
                <div class="user-link-disabled">
                    @if($responseUser->profile_image)
                        @php
                            // アバター画像（public/images/avatars/）の場合はasset()を使用
                            // それ以外（storage/）の場合はStorage::url()を使用（S3対応）
                            if (strpos($responseUser->profile_image, 'avatars/') !== false || strpos($responseUser->profile_image, 'images/avatars/') !== false) {
                                $imageUrl = asset($responseUser->profile_image);
                            } else {
                                $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($responseUser->profile_image);
                            }
                        @endphp
                        <img src="{{ $imageUrl }}" alt="{{ $responseUser->username }}" class="user-avatar">
                    @else
                        <div class="user-avatar-placeholder">👤</div>
                    @endif
                    <span class="response-user my-username">{{ $displayUserName }}</span>
                    @if($nationalityCode || $residenceCode)
                        <span class="country-flags">
                            @if($nationalityCode && $residenceCode)
                                {!! $renderCountryFlag($nationalityCode) !!}
                                <span class="flag-arrow">→</span>
                                {!! $renderCountryFlag($residenceCode) !!}
                            @elseif($nationalityCode)
                                {!! $renderCountryFlag($nationalityCode) !!}
                            @elseif($residenceCode)
                                {!! $renderCountryFlag($residenceCode) !!}
                            @endif
                        </span>
                    @endif
                </div>
            @else
                @php
                    $isMyProfile = auth()->check() && auth()->user()->user_id === ($responseUser->user_id ?? null);
                @endphp
                @if($isMyProfile)
                    <span class="user-link user-link-inline-flex">
                        @if($responseUser->profile_image)
                            @php
                                $imageUrl = (strpos($responseUser->profile_image, 'avatars/') !== false || strpos($responseUser->profile_image, 'images/avatars/') !== false)
                                    ? asset($responseUser->profile_image) 
                                    : asset('storage/' . $responseUser->profile_image);
                            @endphp
                            <img src="{{ $imageUrl }}" alt="{{ $responseUser->username }}" class="user-avatar">
                        @else
                            <div class="user-avatar-placeholder">👤</div>
                        @endif
                        <span class="response-user">{{ $displayUserName }}</span>
                        @if($nationalityCode || $residenceCode)
                            <span class="country-flags">
                                @if($nationalityCode && $residenceCode)
                                    {!! $renderCountryFlag($nationalityCode) !!}
                                    <span class="flag-arrow">→</span>
                                    {!! $renderCountryFlag($residenceCode) !!}
                                @elseif($nationalityCode)
                                    {!! $renderCountryFlag($nationalityCode) !!}
                                @elseif($residenceCode)
                                    {!! $renderCountryFlag($residenceCode) !!}
                                @endif
                            </span>
                        @endif
                    </span>
                @else
                    <a href="{{ route('profile.show', $responseUser->user_id) }}" class="user-link">
                        @if($responseUser->profile_image)
                            @php
                                $imageUrl = (strpos($responseUser->profile_image, 'avatars/') !== false || strpos($responseUser->profile_image, 'images/avatars/') !== false)
                                    ? asset($responseUser->profile_image) 
                                    : asset('storage/' . $responseUser->profile_image);
                            @endphp
                            <img src="{{ $imageUrl }}" alt="{{ $responseUser->username }}" class="user-avatar">
                        @else
                            <div class="user-avatar-placeholder">👤</div>
                        @endif
                        <span class="response-user">{{ $displayUserName }}</span>
                        @if($nationalityCode || $residenceCode)
                            <span class="country-flags">
                                @if($nationalityCode && $residenceCode)
                                    {!! $renderCountryFlag($nationalityCode) !!}
                                    <span class="flag-arrow">→</span>
                                    {!! $renderCountryFlag($residenceCode) !!}
                                @elseif($nationalityCode)
                                    {!! $renderCountryFlag($nationalityCode) !!}
                                @elseif($residenceCode)
                                    {!! $renderCountryFlag($residenceCode) !!}
                                @endif
                            </span>
                        @endif
                    </a>
                @endif
            @endif
        @else
            <span class="response-user {{ $isMyResponse ? 'my-username' : '' }}">{{ $displayUserName }}</span>
            @if($nationalityCode || $residenceCode)
                <span class="country-flags">
                    @if($nationalityCode && $residenceCode)
                        {!! $renderCountryFlag($nationalityCode) !!}
                        <span class="flag-arrow">→</span>
                        {!! $renderCountryFlag($residenceCode) !!}
                    @elseif($nationalityCode)
                        {!! $renderCountryFlag($nationalityCode) !!}
                    @elseif($residenceCode)
                        {!! $renderCountryFlag($residenceCode) !!}
                    @endif
                </span>
            @endif
        @endif
        
        @if($response->parentResponse)
            <span class="reply-indicator">{{ \App\Services\LanguageService::trans('reply_indicator', $lang) }}</span>
        @endif
    </div>
    
    @if(!empty($response->body))
        <div class="response-body">{!! linkify_urls($response->display_body ?? $response->body) !!}</div>
    @endif
    
    @if($response->media_file)
        <div class="response-media-line-style">
            @if($response->media_type === 'image')
                @php
                    // データベースに保存されているパスを取得
                    $mediaFileDb = $response->media_file;
                    
                    // パスの正規化（過去のデータ形式に対応）
                    if (strpos($mediaFileDb, 'storage/') === 0) {
                        // 過去のデータ形式: storage/response_media/...
                        $storagePath = str_replace('storage/', '', $mediaFileDb);
                    } else {
                        // 新しいデータ形式: response_media/...
                        $storagePath = $mediaFileDb;
                    }
                    
                    // Storage::url()を使用（S3対応、APP_URL/storage/response_media/...を生成）
                    $imageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($storagePath);
                    
                    // ファイルの存在確認（Storageファサードを使用してS3対応）
                    $disk = \Illuminate\Support\Facades\Storage::disk('public');
                    $fileExists = $disk->exists($storagePath);
                    
                    // デバッグ用ログ（常に出力）
                    \Log::info('Response image debug', [
                        'response_id' => $response->response_id,
                        'media_file_db' => $mediaFileDb,
                        'storage_path' => $storagePath,
                        'file_exists' => $fileExists,
                        'image_url' => $imageUrl,
                    ]);
                    
                    // ファイルが存在しない場合の警告
                    if (!$fileExists) {
                        \Log::warning('Response image file not found', [
                            'response_id' => $response->response_id,
                            'media_file_db' => $mediaFileDb,
                            'storage_path' => $storagePath,
                        ]);
                    }
                @endphp
                <div class="media-preview-image" onclick="openImageModal('{{ $imageUrl }}')">
                    <img src="{{ $imageUrl }}" alt="添付画像" class="media-thumbnail" onerror="console.error('Image failed to load:', '{{ $imageUrl }}'); console.error('Media file value (DB):', '{{ $response->media_file }}'); console.error('Storage path:', '{{ $storagePath }}'); console.error('File exists:', {{ $fileExists ? 'true' : 'false' }}); this.style.display='none';">
                    <div class="media-overlay">
                        <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                            <circle cx="12" cy="12" r="3"></circle>
                        </svg>
                    </div>
                </div>
            @elseif($response->media_type === 'video')
                @php
                    // データベースに保存されているパスを取得
                    $mediaFileDb = $response->media_file;
                    
                    // パスの正規化（過去のデータ形式に対応）
                    if (strpos($mediaFileDb, 'storage/') === 0) {
                        // 過去のデータ形式: storage/response_media/...
                        $storagePath = str_replace('storage/', '', $mediaFileDb);
                    } else {
                        // 新しいデータ形式: response_media/...
                        $storagePath = $mediaFileDb;
                    }
                    
                    // Storage::url()を使用（S3対応）
                    $videoUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($storagePath);
                @endphp
                <div class="media-preview-video">
                    <video class="media-video-thumbnail" preload="metadata" onclick="toggleVideoPlay(this)" data-video-src="{{ $videoUrl }}">
                        <source src="{{ $videoUrl }}" type="video/{{ pathinfo($response->media_file, PATHINFO_EXTENSION) === 'webm' ? 'webm' : 'mp4' }}">
                        {{ \App\Services\LanguageService::trans('video_not_supported', $lang) }}
                    </video>
                    <div class="media-video-overlay" onclick="toggleVideoPlay(this.previousElementSibling)">
                        <svg width="48" height="48" viewBox="0 0 24 24" fill="white">
                            <path d="M8 5v14l11-7z"/>
                        </svg>
                    </div>
                </div>
            @elseif($response->media_type === 'audio')
                @php
                    // データベースに保存されているパスを取得
                    $mediaFileDb = $response->media_file;
                    
                    // パスの正規化（過去のデータ形式に対応）
                    if (strpos($mediaFileDb, 'storage/') === 0) {
                        // 過去のデータ形式: storage/response_media/...
                        $storagePath = str_replace('storage/', '', $mediaFileDb);
                    } else {
                        // 新しいデータ形式: response_media/...
                        $storagePath = $mediaFileDb;
                    }
                    
                    // Storage::url()を使用（S3対応）
                    $audioUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($storagePath);
                @endphp
                <div class="media-preview-audio">
                    <audio controls class="audio-player" preload="metadata">
                        <source src="{{ $audioUrl }}" type="audio/{{ pathinfo($response->media_file, PATHINFO_EXTENSION) === 'mp3' ? 'mpeg' : (pathinfo($response->media_file, PATHINFO_EXTENSION) === 'm4a' ? 'mp4' : 'webm') }}">
                        {{ \App\Services\LanguageService::trans('audio_not_supported', $lang) }}
                    </audio>
                </div>
            @endif
        </div>
    @endif
    
    <div class="response-actions">
        <div class="response-time" data-utc-datetime="{{ $response->created_at->format('Y-m-d H:i:s') }}" data-format="en">{{ $response->created_at->format('Y-m-d H:i') }}</div>
        <div class="response-actions-buttons">
            <button class="reply-btn" data-response-id="{{ $response->response_id }}" data-user-name="{{ $username }}" data-response-body="{{ $response->body }}">
                {{ \App\Services\LanguageService::trans('reply_button', $lang) }}
            </button>
            @auth
                @if(isset($isReported) && $isReported)
                    @if(isset($isReportRejected) && $isReportRejected)
                        <span class="report-btn reported-badge">{{ \App\Services\LanguageService::trans('reported', $lang) }}</span>
                    @else
                        <button type="button" class="report-btn" onclick="openReportModal(null, {{ $response->response_id }})">{{ \App\Services\LanguageService::trans('report_change', $lang) }}</button>
                    @endif
                @else
                    <button type="button" class="report-btn" onclick="openReportModal(null, {{ $response->response_id }})">{{ \App\Services\LanguageService::trans('report', $lang) }}</button>
                @endif
            @endauth
        </div>
    </div>


</article>
@endif
