<?php

namespace App\Http\Controllers;

use App\Models\Response;
use App\Models\Thread;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Gate;
use App\Services\SafeBrowsingService;
use App\Services\MediaFileValidationService;
use App\Services\SpamDetectionService;

class ResponseController extends Controller
{
    /**
     * 新しいレスポンスをデータベースに保存する
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Thread  $thread
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request, Thread $thread)
    {
        // IDOR防止: レスポンスを作成する権限をチェック
        if (!auth()->check()) {
            $intendedUrl = route('threads.show', $thread);
            session(['intended_url' => $intendedUrl]);
            return redirect()->route('auth.choice');
        }
        Gate::authorize('create', Response::class);

        if (auth()->user()->isFrozen()) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();

            return back()->withErrors(['body' => auth()->user()->frozenPostDeniedMessage($lang)])->withInput();
        }

        // 重複実行防止
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('response.store', auth()->user()->user_id, (string) $thread->thread_id);
        if (!$lock) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return back()->withErrors(['body' => \App\Services\LanguageService::trans('duplicate_submission', $lang)])->withInput();
        }
        try {

        // $_FILESを直接チェック（PHPの設定が原因でファイルが到達しない場合の診断用）
        $filesSuperglobal = isset($_FILES['media_file']) ? [
            'name' => $_FILES['media_file']['name'] ?? 'not set',
            'type' => $_FILES['media_file']['type'] ?? 'not set',
            'size' => $_FILES['media_file']['size'] ?? 0,
            'tmp_name' => isset($_FILES['media_file']['tmp_name']) ? 'set' : 'not set',
            'error' => $_FILES['media_file']['error'] ?? 'not set',
        ] : 'not set';
        
        \Log::info('ResponseController: store method called', [
            'thread_id' => $thread->id,
            'user_authenticated' => auth()->check(),
            'current_session_id' => session()->getId(),
            'has_file' => $request->hasFile('media_file'),
            'has_body' => !empty($request->body),
            'request_method' => $request->method(),
            'content_type' => $request->header('Content-Type'),
            'content_length' => $request->header('Content-Length'),
            'all_files' => $request->allFiles(),
            'input_keys' => array_keys($request->all()),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            '_files_media_file' => $filesSuperglobal,
            'post_data_empty' => empty($_POST),
        ]);

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        // ログインしていない場合は元のURLをセッションに保存して会員登録ページにリダイレクト
        if (!auth()->check()) {
            $intendedUrl = route('threads.show', $thread);
            session(['intended_url' => $intendedUrl]);
            \Log::info('ResponseController: intended_url saved', [
                'url' => $intendedUrl,
                'session_id' => session()->getId(),
                'session_data' => session()->all()
            ]);
            return redirect()->route('auth.choice')->with('message', \App\Services\LanguageService::trans('login_required_for_comment', $lang));
        }

        // スレッドが制限されている場合は常に投稿を拒否（了承しても投稿不可）
        if ($thread->isRestricted()) {
            return back()->withErrors(['restricted' => \App\Services\LanguageService::trans('thread_restricted_no_post', $lang)]);
        }

        // 通報による制限中の追加制限（ファイル/URL）
        $limits = new \App\Services\ReportRestrictionLimitsService();
        $userIdForLimits = auth()->user()->user_id;
        if ($request->hasFile('media_file')) {
            $fileLimit = $limits->fileUploadLimitPerDay((int) $userIdForLimits);
            $todayFiles = $limits->todayFileUploadCount((int) $userIdForLimits);
            if ($todayFiles >= $fileLimit) {
                return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('report_restriction_file_limit_exceeded', $lang)]);
            }
        }

        // レスポンス数上限チェック
        $maxResponses = config('performance.thread.max_responses', 60);
        $currentResponseCount = $thread->responses()->count();
        if ($currentResponseCount >= $maxResponses) {
            return back()->withErrors(['body' => \App\Services\LanguageService::trans('thread_response_limit_reached', $lang)]);
        }

        $currentUser = auth()->user();
        $maxBodyLength = $currentUser->responseBodyMaxLength();

        // フォームから送信されたリクエストデータを検証します。
        // bodyまたはmedia_fileのいずれかが必要
        \Log::info('ResponseController: Before validation (store)', [
            'has_file' => $request->hasFile('media_file'),
            'all_files' => $request->allFiles(),
            'input_keys' => array_keys($request->all()),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'max_file_uploads' => ini_get('max_file_uploads'),
        ]);
        
        // ファイルが存在する場合、詳細情報をログに記録
        if ($request->hasFile('media_file')) {
            try {
                $file = $request->file('media_file');
                \Log::info('ResponseController: File details (store)', [
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'extension' => $file->getClientOriginalExtension(),
                    'is_valid' => $file->isValid(),
                    'error' => $file->getError(),
                ]);
            } catch (\Exception $e) {
                \Log::error('ResponseController: Error getting file details (store)', [
                    'error' => $e->getMessage(),
                ]);
            }
        } else {
            \Log::warning('ResponseController: No file uploaded (store)', [
                'all_files' => $request->allFiles(),
                'input_keys' => array_keys($request->all()),
            ]);
        }
        
        // バリデーションルールを動的に設定（ファイルが存在しない場合はfileルールを適用しない）
        $rules = [
            'body' => 'nullable|string|max:' . $maxBodyLength,
        ];
        
        if ($request->hasFile('media_file')) {
            $rules['media_file'] = 'file|max:' . (10 * 1024); // 10MB (キロバイト単位)
        } else {
            $rules['media_file'] = 'nullable';
        }
        
        try {
            $request->validate($rules, [
                'media_file.file' => \App\Services\LanguageService::trans('media_file_upload_failed', $lang),
                'media_file.max' => \App\Services\LanguageService::trans('media_file_too_large', $lang),
            ]);
        } catch (\Illuminate\Validation\ValidationException $e) {
            \Log::error('ResponseController: Validation failed (store)', [
                'errors' => $e->errors(),
                'message' => $e->getMessage(),
                'has_file' => $request->hasFile('media_file'),
                'all_files' => $request->allFiles(),
            ]);
            return back()->withInput()->withErrors($e->errors());
        }

        // bodyまたはmedia_fileのいずれかが必要
        if (empty($request->body) && !$request->hasFile('media_file')) {
            // post_max_sizeを超えている可能性をチェック
            $contentLength = $request->header('Content-Length');
            $postMaxSizeBytes = $this->convertToBytes(ini_get('post_max_size'));
            
            if ($contentLength && $contentLength > $postMaxSizeBytes) {
                $uploadMaxSize = ini_get('upload_max_filesize');
                $postMaxSize = ini_get('post_max_size');
                $contentLengthMB = round($contentLength / 1024 / 1024, 2);
                $postMaxSizeMB = round($postMaxSizeBytes / 1024 / 1024, 2);
                
                \Log::warning('ResponseController: Request size exceeds post_max_size (store)', [
                    'content_length' => $contentLength,
                    'content_length_mb' => $contentLengthMB,
                    'post_max_size_bytes' => $postMaxSizeBytes,
                    'post_max_size_mb' => $postMaxSizeMB,
                    'upload_max_filesize' => $uploadMaxSize,
                    'post_max_size' => $postMaxSize,
                ]);
                
                if ($lang === 'ja') {
                    $errorMessage = "リクエストサイズがPHPの設定上限を超えています。リクエストサイズ：{$contentLengthMB}MB、POST上限：{$postMaxSize}（約{$postMaxSizeMB}MB）、アップロード上限：{$uploadMaxSize}。ファイルサイズを小さくするか、サーバー管理者にご連絡ください。";
                } else {
                    $errorMessage = "Request size exceeds PHP configuration limits. Request size: {$contentLengthMB}MB, POST limit: {$postMaxSize} (approx. {$postMaxSizeMB}MB), Upload limit: {$uploadMaxSize}. Please reduce the file size or contact the server administrator.";
                }
                return back()->withInput()->withErrors(['media_file' => $errorMessage]);
            }
            
            // ファイルが選択されていたがアップロードされなかった場合、PHPのエラーコードをチェック
            if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadError = $_FILES['media_file']['error'];
                $uploadMaxSize = ini_get('upload_max_filesize');
                $postMaxSize = ini_get('post_max_size');
                
                \Log::warning('ResponseController: File upload error detected (store)', [
                    'error_code' => $uploadError,
                    'upload_max_filesize' => $uploadMaxSize,
                    'post_max_size' => $postMaxSize,
                    'file_name' => $_FILES['media_file']['name'] ?? 'unknown',
                    'file_size' => $_FILES['media_file']['size'] ?? 0,
                ]);
                
                // PHPの設定上限を超えた場合
                if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                    $fileSize = $_FILES['media_file']['size'] ?? 0;
                    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                    $uploadMaxSizeBytes = $this->convertToBytes($uploadMaxSize);
                    $uploadMaxSizeMB = round($uploadMaxSizeBytes / 1024 / 1024, 2);
                    
                    if ($lang === 'ja') {
                        $errorMessage = "ファイルサイズがPHPの設定上限を超えています。選択されたファイル：{$fileSizeMB}MB、アップロード上限：{$uploadMaxSize}（約{$uploadMaxSizeMB}MB）、POST上限：{$postMaxSize}。ファイルサイズを小さくするか、サーバー管理者にご連絡ください。";
                    } else {
                        $errorMessage = "File size exceeds PHP configuration limits. Selected file: {$fileSizeMB}MB, Upload limit: {$uploadMaxSize} (approx. {$uploadMaxSizeMB}MB), POST limit: {$postMaxSize}. Please reduce the file size or contact the server administrator.";
                    }
                    return back()->withInput()->withErrors(['media_file' => $errorMessage]);
                }
            }
            
            return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('media_file_required', $lang)]);
        }

        if ($currentUser->requiresPhoneVerificationRestrictions() && $request->hasFile('media_file')) {
            return back()->withInput()->withErrors(['body' => '電話番号未登録アカウントではメディア投稿は利用できません。']);
        }

        // ファイルアップロードの処理
        $mediaFile = null;
        $mediaType = null;
        if ($request->hasFile('media_file')) {
            \Log::info('ResponseController: Media file detected (store)', [
                'filename' => $request->file('media_file')->getClientOriginalName(),
                'mime_type' => $request->file('media_file')->getMimeType(),
                'size' => $request->file('media_file')->getSize(),
            ]);
            
            $validationService = new MediaFileValidationService($lang);
            $validationResult = $validationService->validateFile($request->file('media_file'));
            
            \Log::info('ResponseController: Media file validation result (store)', [
                'valid' => $validationResult['valid'],
                'error' => $validationResult['error'] ?? null,
                'media_type' => $validationResult['media_type'] ?? null,
            ]);
            
            if (!$validationResult['valid']) {
                \Log::warning('ResponseController: File validation failed (store)', [
                    'error' => $validationResult['error'],
                ]);
                return back()->withInput()->withErrors(['body' => $validationResult['error']]);
            }
            
            // ファイルを保存
            $file = $request->file('media_file');
            // ユーザー入力のファイル名を直接使わず、hashでリネーム
            $hashedFilename = hash('sha256', time() . $file->getClientOriginalName());
            // MIMEタイプから拡張子を取得（ユーザー入力に依存しない）
            $extension = $this->getExtensionFromMimeType($file->getMimeType(), $validationResult['media_type']);
            $filename = $hashedFilename . '.' . $extension;
            $path = $file->storeAs('response_media', $filename, 'public');
            $mediaFile = $path;
            $mediaType = $validationResult['media_type'];
            
            // ファイルが実際に保存されているか確認（Storageファサードを使用してS3対応）
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            $fileExists = $disk->exists($path);
            $fileSize = $fileExists ? $disk->size($path) : 0;
            
            \Log::info('ResponseController: File uploaded successfully (store)', [
                'path' => $path,
                'file_exists' => $fileExists,
                'file_size' => $fileSize,
                'media_type' => $mediaType,
                'storage_url' => Storage::disk('public')->url($path),
            ]);

            // メディアファイルの処理（画像：再エンコード、動画・音声：メタデータ削除）
            if ($fileExists) {
                $processingService = new \App\Services\MediaFileProcessingService();
                $processingResult = $processingService->processMediaFile($path, $mediaType, 'public');
                
                if (!$processingResult['success']) {
                    \Log::warning('ResponseController: Media file processing failed (store)', [
                        'error' => $processingResult['error'],
                        'media_type' => $mediaType,
                    ]);
                    // 処理に失敗しても続行（ログに記録のみ）
                } else {
                    $newFileSize = $disk->exists($path) ? $disk->size($path) : 0;
                    \Log::info('ResponseController: Media file processed successfully (store)', [
                        'media_type' => $mediaType,
                        'new_size' => $newFileSize,
                    ]);
                }
            }
        }

        // URLの安全性チェック（bodyが存在する場合のみ）
        $body = $request->body ?? '';
        \Log::info('ResponseController: Starting URL safety check (store)', [
            'thread_id' => $thread->thread_id,
            'body_length' => strlen($body)
        ]);
        
        $safeBrowsingService = new SafeBrowsingService();
        $urls = $safeBrowsingService->extractUrls($body);
        
        if (!empty($urls)) {
            if ($currentUser->requiresPhoneVerificationRestrictions()) {
                return back()->withInput()->withErrors(['body' => '電話番号未登録アカウントではURL投稿は利用できません。']);
            }
            $urlLimit = $limits->urlPostLimitPerDay((int) $userIdForLimits);
            $todayUrls = $limits->todayUrlPostCount((int) $userIdForLimits);
            if ($todayUrls >= $urlLimit) {
                return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('report_restriction_url_limit_exceeded', $lang)]);
            }

            \Log::info('ResponseController: URLs found in response (store)', [
                'url_count' => count($urls),
                'urls' => $urls
            ]);
            
            foreach ($urls as $url) {
                $checkResult = $safeBrowsingService->checkUrl($url);
                
                \Log::info('ResponseController: URL check result (store)', [
                    'url' => $url,
                    'safe' => $checkResult['safe'],
                    'error' => $checkResult['error'] ?? null,
                    'threats' => $checkResult['threats'] ?? []
                ]);
                
                if (!$checkResult['safe']) {
                    // API利用制限エラーの場合
                    if ($checkResult['error'] === 'rate_limit_exceeded') {
                        \Log::warning('ResponseController: Rate limit exceeded, blocking post (store)');
                        return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('url_check_rate_limit', $lang)]);
                    }
                    
                    // 危険なURLまたはAPIエラーの場合
                    \Log::warning('ResponseController: Unsafe URL or API error detected, blocking post (store)', [
                        'url' => $url,
                        'error' => $checkResult['error'] ?? null,
                        'threats' => $checkResult['threats'] ?? []
                    ]);
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('url_check_unsafe', $lang)]);
                }
            }
        } else {
            \Log::debug('ResponseController: No URLs found in response body (store)');
        }

        // コインを消費（メディア1件ごとに1コイン、URLを除く本文は1〜100文字で1コイン・以降100文字ごとに1コイン）
        // ※ウイルスチェックやSafeBrowsingなどの重い処理を行う前に、最初に確認する
        if (auth()->check()) {
            $coinService = new \App\Services\CoinService();
            $bodyForCost = $request->body ?? '';
            $hasMediaFileForCost = !empty($mediaFile);
            $cost = $coinService->getResponseCost($bodyForCost, $hasMediaFileForCost);
            $user = auth()->user();
            
            if (!$coinService->consumeCoins($user, $cost)) {
                return back()->withErrors(['body' => \App\Services\LanguageService::trans('insufficient_coins', $lang)])
                    ->withInput();
            }
        }

        // スパム判定（bodyが存在する場合のみ）
        if (!empty($body)) {
            \Log::info('ResponseController: Starting spam check (store)', [
                'thread_id' => $thread->thread_id,
                'body_length' => strlen($body),
                'user_name' => auth()->user()->username,
                'url_count' => count($urls ?? []),
            ]);
            
            $spamDetectionService = new SpamDetectionService();
            $spamResult = $spamDetectionService->checkSpam($body, auth()->user()->username, $urls ?? []);
            
            if ($spamResult['is_spam']) {
                \Log::warning('ResponseController: Spam detected, blocking post (store)', [
                    'reason' => $spamResult['reason'],
                    'ng_word' => $spamResult['ng_word'] ?? null,
                    'similarity' => $spamResult['similarity'] ?? null,
                    'url' => $spamResult['url'] ?? null,
                    'count' => $spamResult['count'] ?? null,
                ]);
                
                if ($spamResult['reason'] === 'ng_word') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_ng_word_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'similarity') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_similar_response_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'url_similarity') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_similar_url_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'url_post_limit') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_url_post_limit_exceeded', $lang)]);
                }
            }
        }

        // メディア投稿の1日制限チェック（メディア付き投稿の場合のみ）
        if (!empty($mediaFile) && auth()->check()) {
            $spamDetectionService = new SpamDetectionService();
            $mediaLimitResult = $spamDetectionService->checkMediaPostLimit(auth()->user()->user_id);
            if ($mediaLimitResult['is_spam']) {
                \Log::warning('ResponseController: Media post limit exceeded (store)', [
                    'user_id' => auth()->id(),
                    'count' => $mediaLimitResult['count'] ?? null,
                ]);
                return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_media_post_limit_exceeded', $lang)]);
            }
        }

        // レスポンス番号を計算（既存のレスポンス数 + 1）
        $responsesNum = $thread->responses()->count() + 1;

        // レスポンスを作成し、スレッドに関連付けます。
        \Log::info('ResponseController: Creating response (store)', [
            'media_file' => $mediaFile,
            'media_type' => $mediaType,
            'body' => $request->body ?? '',
        ]);
        
        if (!auth()->check()) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return back()->withErrors(['body' => \App\Services\LanguageService::trans('login_required', $lang)])
                ->withInput();
        }
        
        $userId = auth()->user()->user_id;
        $sendTimeLang = \App\Services\TranslationService::normalizeLang(auth()->user()->language ?? 'EN');

        $parentResponseId = $request->parent_response_id ? (int) $request->parent_response_id : null;
        $parentSnapshotUsername = null;
        $parentSnapshotBody = null;
        if ($parentResponseId) {
            $parent = Response::where('thread_id', $thread->thread_id)
                ->where('response_id', $parentResponseId)
                ->first();
            if ($parent) {
                $parentUser = $parent->user_id ? \App\Models\User::find($parent->user_id) : null;
                $parentSnapshotUsername = $parentUser?->username ?? '削除されたユーザー';
                $parentSnapshotBody = $parent->body ? mb_strimwidth(strip_tags((string) $parent->body), 0, 200, '…') : null;
            } else {
                // 不正な親IDは無視
                $parentResponseId = null;
            }
        }

        $createdResponse = $thread->responses()->create([
            'user_id' => $userId,
            'body' => $request->body ?? '',
            'source_lang' => $sendTimeLang,
            'responses_num' => $responsesNum,
            'parent_response_id' => $parentResponseId,
            'parent_original_response_id' => $parentResponseId,
            'parent_snapshot_username' => $parentSnapshotUsername,
            'parent_snapshot_body' => $parentSnapshotBody,
            'media_file' => $mediaFile,
            'media_type' => $mediaType,
        ]);
        
        \Log::info('ResponseController: Response created (store)', [
            'response_id' => $createdResponse->response_id,
            'saved_media_file' => $createdResponse->media_file,
            'saved_media_type' => $createdResponse->media_type,
        ]);

        // スレッド内での相互送信を追跡（フレンド申請条件のため）
        if (auth()->check()) {
            app(\App\Services\ThreadDirectedInteractionService::class)->syncInteractionsForUserInThread($thread, auth()->user());
        }

        // 関連するキャッシュをクリア
        $this->clearRelatedCaches($thread);

        // レスポンスを保存した後、スレッド詳細ページにリダイレクトします。
        return redirect()->route('threads.show', $thread);
        } finally {
            $lock->release();
        }
    }

    /**
     * レスポンスに返信する
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Thread  $thread
     * @param  \App\Models\Response  $response
     * @return \Illuminate\Http\RedirectResponse
     */
    public function reply(Request $request, Thread $thread, Response $response)
    {
        // IDOR防止: レスポンスを作成する権限をチェック
        if (!auth()->check()) {
            $intendedUrl = route('threads.show', $thread);
            session(['intended_url' => $intendedUrl]);
            return redirect()->route('auth.choice');
        }
        Gate::authorize('create', Response::class);

        if (auth()->user()->isFrozen()) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();

            return back()->withErrors(['body' => auth()->user()->frozenPostDeniedMessage($lang)])->withInput();
        }

        // 重複実行防止
        $resourceId = $thread->thread_id . ':' . $response->response_id;
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('response.reply', auth()->user()->user_id, $resourceId);
        if (!$lock) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return back()->withErrors(['body' => \App\Services\LanguageService::trans('duplicate_submission', $lang)])->withInput();
        }
        try {

        // $_FILESを直接チェック（PHPの設定が原因でファイルが到達しない場合の診断用）
        $filesSuperglobal = isset($_FILES['media_file']) ? [
            'name' => $_FILES['media_file']['name'] ?? 'not set',
            'type' => $_FILES['media_file']['type'] ?? 'not set',
            'size' => $_FILES['media_file']['size'] ?? 0,
            'tmp_name' => isset($_FILES['media_file']['tmp_name']) ? 'set' : 'not set',
            'error' => $_FILES['media_file']['error'] ?? 'not set',
        ] : 'not set';
        
        \Log::info('ResponseController: reply method called', [
            'thread_id' => $thread->id,
            'response_id' => $response->response_id,
            'has_file' => $request->hasFile('media_file'),
            'content_length' => $request->header('Content-Length'),
            'post_max_size' => ini_get('post_max_size'),
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            '_files_media_file' => $filesSuperglobal,
        ]);

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        // ログインしていない場合は元のURLをセッションに保存して会員登録ページにリダイレクト
        if (!auth()->check()) {
            $intendedUrl = route('threads.show', $thread);
            session(['intended_url' => $intendedUrl]);
            return redirect()->route('auth.choice')->with('message', \App\Services\LanguageService::trans('login_required_for_comment', $lang));
        }

        // スレッドが制限されている場合は常に投稿を拒否（了承しても投稿不可）
        if ($thread->isRestricted()) {
            return back()->withErrors(['restricted' => \App\Services\LanguageService::trans('thread_restricted_no_post', $lang)]);
        }

        // 通報による制限中の追加制限（ファイル/URL）
        $limits = new \App\Services\ReportRestrictionLimitsService();
        $userIdForLimits = auth()->user()->user_id;
        if ($request->hasFile('media_file')) {
            $fileLimit = $limits->fileUploadLimitPerDay((int) $userIdForLimits);
            $todayFiles = $limits->todayFileUploadCount((int) $userIdForLimits);
            if ($todayFiles >= $fileLimit) {
                return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('report_restriction_file_limit_exceeded', $lang)]);
            }
        }

        // レスポンス数上限チェック
        $maxResponses = config('performance.thread.max_responses', 60);
        $currentResponseCount = $thread->responses()->count();
        if ($currentResponseCount >= $maxResponses) {
            return back()->withErrors(['body' => \App\Services\LanguageService::trans('thread_response_limit_reached', $lang)]);
        }

        $currentUser = auth()->user();
        $maxBodyLength = $currentUser->responseBodyMaxLength();

        // フォームから送信されたリクエストデータを検証します。
        // bodyまたはmedia_fileのいずれかが必要。本文は500文字まで。メディアは10MBまで。
        $request->validate([
            'body' => 'nullable|string|max:' . $maxBodyLength,
            'media_file' => 'nullable|file|max:' . (10 * 1024),
        ], [
            'media_file.file' => \App\Services\LanguageService::trans('media_file_upload_failed', $lang),
            'media_file.max' => \App\Services\LanguageService::trans('media_file_too_large', $lang),
        ]);

        // bodyまたはmedia_fileのいずれかが必要
        if (empty($request->body) && !$request->hasFile('media_file')) {
            // post_max_sizeを超えている可能性をチェック
            $contentLength = $request->header('Content-Length');
            $postMaxSizeBytes = $this->convertToBytes(ini_get('post_max_size'));
            
            if ($contentLength && $contentLength > $postMaxSizeBytes) {
                $uploadMaxSize = ini_get('upload_max_filesize');
                $postMaxSize = ini_get('post_max_size');
                $contentLengthMB = round($contentLength / 1024 / 1024, 2);
                $postMaxSizeMB = round($postMaxSizeBytes / 1024 / 1024, 2);
                
                \Log::warning('ResponseController: Request size exceeds post_max_size (reply)', [
                    'content_length' => $contentLength,
                    'content_length_mb' => $contentLengthMB,
                    'post_max_size_bytes' => $postMaxSizeBytes,
                    'post_max_size_mb' => $postMaxSizeMB,
                    'upload_max_filesize' => $uploadMaxSize,
                    'post_max_size' => $postMaxSize,
                ]);
                
                if ($lang === 'ja') {
                    $errorMessage = "リクエストサイズがPHPの設定上限を超えています。リクエストサイズ：{$contentLengthMB}MB、POST上限：{$postMaxSize}（約{$postMaxSizeMB}MB）、アップロード上限：{$uploadMaxSize}。ファイルサイズを小さくするか、サーバー管理者にご連絡ください。";
                } else {
                    $errorMessage = "Request size exceeds PHP configuration limits. Request size: {$contentLengthMB}MB, POST limit: {$postMaxSize} (approx. {$postMaxSizeMB}MB), Upload limit: {$uploadMaxSize}. Please reduce the file size or contact the server administrator.";
                }
                return back()->withInput()->withErrors(['media_file' => $errorMessage]);
            }
            
            // ファイルが選択されていたがアップロードされなかった場合、PHPのエラーコードをチェック
            if (isset($_FILES['media_file']) && $_FILES['media_file']['error'] !== UPLOAD_ERR_NO_FILE) {
                $uploadError = $_FILES['media_file']['error'];
                $uploadMaxSize = ini_get('upload_max_filesize');
                $postMaxSize = ini_get('post_max_size');
                
                \Log::warning('ResponseController: File upload error detected (reply)', [
                    'error_code' => $uploadError,
                    'upload_max_filesize' => $uploadMaxSize,
                    'post_max_size' => $postMaxSize,
                    'file_name' => $_FILES['media_file']['name'] ?? 'unknown',
                    'file_size' => $_FILES['media_file']['size'] ?? 0,
                ]);
                
                // PHPの設定上限を超えた場合
                if ($uploadError === UPLOAD_ERR_INI_SIZE || $uploadError === UPLOAD_ERR_FORM_SIZE) {
                    $fileSize = $_FILES['media_file']['size'] ?? 0;
                    $fileSizeMB = round($fileSize / 1024 / 1024, 2);
                    $uploadMaxSizeBytes = $this->convertToBytes($uploadMaxSize);
                    $uploadMaxSizeMB = round($uploadMaxSizeBytes / 1024 / 1024, 2);
                    
                    if ($lang === 'ja') {
                        $errorMessage = "ファイルサイズがPHPの設定上限を超えています。選択されたファイル：{$fileSizeMB}MB、アップロード上限：{$uploadMaxSize}（約{$uploadMaxSizeMB}MB）、POST上限：{$postMaxSize}。ファイルサイズを小さくするか、サーバー管理者にご連絡ください。";
                    } else {
                        $errorMessage = "File size exceeds PHP configuration limits. Selected file: {$fileSizeMB}MB, Upload limit: {$uploadMaxSize} (approx. {$uploadMaxSizeMB}MB), POST limit: {$postMaxSize}. Please reduce the file size or contact the server administrator.";
                    }
                    return back()->withInput()->withErrors(['media_file' => $errorMessage]);
                }
            }
            
            return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('media_file_required', $lang)]);
        }

        if ($currentUser->requiresPhoneVerificationRestrictions() && $request->hasFile('media_file')) {
            return back()->withInput()->withErrors(['body' => '電話番号未登録アカウントではメディア投稿は利用できません。']);
        }

        // コインを消費（メディア1件ごとに1コイン、URLを除く本文は1〜100文字で1コイン・以降100文字ごとに1コイン）
        // ※ウイルスチェックやSafeBrowsingなどの重い処理を行う前に、最初に確認する
        if (auth()->check()) {
            $coinService = new \App\Services\CoinService();
            $bodyForCost = $request->body ?? '';
            $hasMediaFileForCost = $request->hasFile('media_file');
            $cost = $coinService->getResponseCost($bodyForCost, $hasMediaFileForCost);
            $user = auth()->user();
            
            if (!$coinService->consumeCoins($user, $cost)) {
                return back()->withErrors(['body' => \App\Services\LanguageService::trans('insufficient_coins', $lang)])
                    ->withInput();
            }
        }

        // ファイルアップロードの処理
        $mediaFile = null;
        $mediaType = null;
        if ($request->hasFile('media_file')) {
            \Log::info('ResponseController: Media file detected (reply)', [
                'filename' => $request->file('media_file')->getClientOriginalName(),
                'mime_type' => $request->file('media_file')->getMimeType(),
                'size' => $request->file('media_file')->getSize(),
            ]);
            
            $validationService = new MediaFileValidationService($lang);
            $validationResult = $validationService->validateFile($request->file('media_file'));
            
            \Log::info('ResponseController: Media file validation result (reply)', [
                'valid' => $validationResult['valid'],
                'error' => $validationResult['error'] ?? null,
                'media_type' => $validationResult['media_type'] ?? null,
            ]);
            
            if (!$validationResult['valid']) {
                \Log::warning('ResponseController: File validation failed (reply)', [
                    'error' => $validationResult['error'],
                ]);
                return back()->withInput()->withErrors(['body' => $validationResult['error']]);
            }
            
            // ファイルを保存
            $file = $request->file('media_file');
            // ユーザー入力のファイル名を直接使わず、hashでリネーム
            $hashedFilename = hash('sha256', time() . $file->getClientOriginalName());
            // MIMEタイプから拡張子を取得（ユーザー入力に依存しない）
            $extension = $this->getExtensionFromMimeType($file->getMimeType(), $validationResult['media_type']);
            $filename = $hashedFilename . '.' . $extension;
            $path = $file->storeAs('response_media', $filename, 'public');
            $mediaFile = $path;
            $mediaType = $validationResult['media_type'];
            
            // ファイルが実際に保存されているか確認（Storageファサードを使用してS3対応）
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            $fileExists = $disk->exists($path);
            $fileSize = $fileExists ? $disk->size($path) : 0;
            
            \Log::info('ResponseController: File uploaded successfully (reply)', [
                'path' => $path,
                'file_exists' => $fileExists,
                'file_size' => $fileSize,
                'media_type' => $mediaType,
                'storage_url' => Storage::disk('public')->url($path),
            ]);

            // メディアファイルの処理（画像：再エンコード、動画・音声：メタデータ削除）
            if ($fileExists) {
                $processingService = new \App\Services\MediaFileProcessingService();
                $processingResult = $processingService->processMediaFile($path, $mediaType, 'public');
                
                if (!$processingResult['success']) {
                    \Log::warning('ResponseController: Media file processing failed (reply)', [
                        'error' => $processingResult['error'],
                        'media_type' => $mediaType,
                    ]);
                    // 処理に失敗しても続行（ログに記録のみ）
                } else {
                    $newFileSize = $disk->exists($path) ? $disk->size($path) : 0;
                    \Log::info('ResponseController: Media file processed successfully (reply)', [
                        'media_type' => $mediaType,
                        'new_size' => $newFileSize,
                    ]);
                }
            }
        }

        // URLの安全性チェック
        \Log::info('ResponseController: Starting URL safety check (reply)', [
            'thread_id' => $thread->thread_id,
            'response_id' => $response->response_id,
            'body_length' => strlen($request->body ?? '')
        ]);
        
        $safeBrowsingService = new SafeBrowsingService();
        $body = $request->body ?? '';
        $urls = $safeBrowsingService->extractUrls($body);
        
        if (!empty($urls)) {
            if ($currentUser->requiresPhoneVerificationRestrictions()) {
                return back()->withInput()->withErrors(['body' => '電話番号未登録アカウントではURL投稿は利用できません。']);
            }
            $urlLimit = $limits->urlPostLimitPerDay((int) $userIdForLimits);
            $todayUrls = $limits->todayUrlPostCount((int) $userIdForLimits);
            if ($todayUrls >= $urlLimit) {
                return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('report_restriction_url_limit_exceeded', $lang)]);
            }

            \Log::info('ResponseController: URLs found in response (reply)', [
                'url_count' => count($urls),
                'urls' => $urls
            ]);
            
            foreach ($urls as $url) {
                $checkResult = $safeBrowsingService->checkUrl($url);
                
                \Log::info('ResponseController: URL check result (reply)', [
                    'url' => $url,
                    'safe' => $checkResult['safe'],
                    'error' => $checkResult['error'] ?? null,
                    'threats' => $checkResult['threats'] ?? []
                ]);
                
                if (!$checkResult['safe']) {
                    // API利用制限エラーの場合
                    if ($checkResult['error'] === 'rate_limit_exceeded') {
                        \Log::warning('ResponseController: Rate limit exceeded, blocking post (reply)');
                        return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('url_check_rate_limit', $lang)]);
                    }
                    
                    // 危険なURLまたはAPIエラーの場合
                    \Log::warning('ResponseController: Unsafe URL or API error detected, blocking post (reply)', [
                        'url' => $url,
                        'error' => $checkResult['error'] ?? null,
                        'threats' => $checkResult['threats'] ?? []
                    ]);
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('url_check_unsafe', $lang)]);
                }
            }
        } else {
            \Log::debug('ResponseController: No URLs found in response body (reply)');
        }

        // スパム判定（bodyが存在する場合のみ）
        if (!empty($body)) {
            \Log::info('ResponseController: Starting spam check (reply)', [
                'thread_id' => $thread->thread_id,
                'response_id' => $response->response_id,
                'body_length' => strlen($body),
                'user_name' => auth()->user()->username,
                'url_count' => count($urls ?? []),
            ]);
            
            $spamDetectionService = new SpamDetectionService();
            $spamResult = $spamDetectionService->checkSpam($body, auth()->user()->username, $urls ?? []);
            
            if ($spamResult['is_spam']) {
                \Log::warning('ResponseController: Spam detected, blocking post (reply)', [
                    'reason' => $spamResult['reason'],
                    'ng_word' => $spamResult['ng_word'] ?? null,
                    'similarity' => $spamResult['similarity'] ?? null,
                    'url' => $spamResult['url'] ?? null,
                    'count' => $spamResult['count'] ?? null,
                ]);
                
                if ($spamResult['reason'] === 'ng_word') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_ng_word_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'similarity') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_similar_response_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'url_similarity') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_similar_url_detected', $lang)]);
                } else                if ($spamResult['reason'] === 'url_post_limit') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_url_post_limit_exceeded', $lang)]);
                }
            }
        }

        // メディア投稿の1日制限チェック（メディア付き投稿の場合のみ）
        if (!empty($mediaFile) && auth()->check()) {
            $spamDetectionService = new SpamDetectionService();
            $mediaLimitResult = $spamDetectionService->checkMediaPostLimit(auth()->user()->user_id);
            if ($mediaLimitResult['is_spam']) {
                \Log::warning('ResponseController: Media post limit exceeded (reply)', [
                    'user_id' => auth()->id(),
                    'count' => $mediaLimitResult['count'] ?? null,
                ]);
                return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_media_post_limit_exceeded', $lang)]);
            }
        }

        // レスポンス番号を計算（既存のレスポンス数 + 1）
        $responsesNum = $thread->responses()->count() + 1;

        // 返信レスポンスを作成
        \Log::info('ResponseController: Creating reply response (reply)', [
            'media_file' => $mediaFile,
            'media_type' => $mediaType,
            'body' => $request->body ?? '',
        ]);
        
        $userId = auth()->check() ? auth()->user()->user_id : null;
        $sendTimeLang = auth()->check()
            ? \App\Services\TranslationService::normalizeLang(auth()->user()->language ?? 'EN')
            : 'EN';

        $parentSnapshotUsername = null;
        $parentSnapshotBody = null;
        try {
            $parentUser = $response->user_id ? \App\Models\User::find($response->user_id) : null;
            $parentSnapshotUsername = $parentUser?->username ?? '削除されたユーザー';
            $parentSnapshotBody = $response->body ? mb_strimwidth(strip_tags((string) $response->body), 0, 200, '…') : null;
        } catch (\Throwable $e) {
            // スナップショットは補助情報なので失敗しても返信投稿自体は継続
            \Log::warning('Failed to build parent snapshot for reply', [
                'thread_id' => $thread->thread_id ?? null,
                'parent_response_id' => $response->response_id ?? null,
                'error' => $e->getMessage(),
            ]);
        }

        $createdResponse = $thread->responses()->create([
            'user_id' => $userId,
            'body' => $request->body ?? '',
            'source_lang' => $sendTimeLang,
            'responses_num' => $responsesNum,
            'parent_response_id' => $response->response_id,
            'parent_original_response_id' => $response->response_id,
            'parent_snapshot_username' => $parentSnapshotUsername,
            'parent_snapshot_body' => $parentSnapshotBody,
            'media_file' => $mediaFile,
            'media_type' => $mediaType,
        ]);
        
        \Log::info('ResponseController: Reply response created (reply)', [
            'response_id' => $createdResponse->response_id,
            'saved_media_file' => $createdResponse->media_file,
            'saved_media_type' => $createdResponse->media_type,
        ]);

        // スレッド内での相互送信を追跡（フレンド申請条件のため）
        if (auth()->check()) {
            app(\App\Services\ThreadDirectedInteractionService::class)->syncInteractionsForUserInThread($thread, auth()->user());
        }

        // 関連するキャッシュをクリア
        $this->clearRelatedCaches($thread);

        // レスポンスを保存した後、スレッド詳細ページにリダイレクトします。
        return redirect()->route('threads.show', $thread);
        } finally {
            $lock->release();
        }
    }

    /**
     * 関連するキャッシュをクリアする
     */
    private function clearRelatedCaches(Thread $thread)
    {
        // スレッド一覧のキャッシュをクリア
        Cache::forget('threads_index_' . md5(''));
        Cache::forget('threads_index_' . md5(null));
        
        // 個別のキャッシュもクリア
        Cache::forget('threads_popular');
        Cache::forget('threads_latest');
        Cache::forget('threads_most_responses');
        
        // タグ関連のキャッシュをクリア
        Cache::forget('threads_tag_' . md5($thread->tag));
        
        // カテゴリ関連のキャッシュもクリア
        $categories = config('thread_categories.categories', []);
        foreach (array_keys($categories) as $category) {
            Cache::forget('category_threads_' . md5($category . '1'));
        }
    }

    /**
     * PHP設定値（例：2M、10M）をバイト数に変換
     *
     * @param string $value
     * @return int
     */
    private function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * MIMEタイプから拡張子を取得（ユーザー入力に依存しない）
     *
     * @param string $mimeType
     * @param string $mediaType
     * @return string
     */
    private function getExtensionFromMimeType(string $mimeType, string $mediaType): string
    {
        $mimeTypeMap = [
            'image' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ],
            'video' => [
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
            ],
            'audio' => [
                'audio/mpeg' => 'mp3',
                'audio/mp4' => 'm4a',
                'audio/webm' => 'webm',
            ],
        ];

        $mimeTypeLower = strtolower($mimeType);
        if (isset($mimeTypeMap[$mediaType][$mimeTypeLower])) {
            return $mimeTypeMap[$mediaType][$mimeTypeLower];
        }

        // フォールバック: メディアタイプに応じたデフォルト拡張子
        return match($mediaType) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'mp3',
            default => 'bin',
        };
    }
}
