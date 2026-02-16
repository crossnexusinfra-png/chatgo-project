<?php

namespace App\Http\Controllers;

use App\Models\Thread;
use App\Models\ThreadContinuationRequest;
use App\Models\AdminMessage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ThreadContinuationController extends Controller
{
    /**
     * 続きスレッドの要望を送信または削除
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Thread  $thread
     * @return \Illuminate\Http\JsonResponse
     */
    public function toggleRequest(Request $request, Thread $thread)
    {
        if (!auth()->check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        // IDOR防止: R18スレッドの閲覧権限をチェック（18歳未満のユーザーは要望不可）
        $currentUser = auth()->user();
        if (!\Illuminate\Support\Facades\Gate::forUser($currentUser)->allows('view', $thread)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return response()->json([
                'error' => \App\Services\LanguageService::trans('r18_thread_adult_only_view', $lang)
            ], 403);
        }

        $user = auth()->user();
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        // レスポンス数が上限に達していない場合は要望できない
        if (!$thread->isResponseLimitReached()) {
            return response()->json([
                'error' => \App\Services\LanguageService::trans('continuation_request_not_available', $lang)
            ], 400);
        }

        // スレッド主かどうかを確認
        $threadOwner = $thread->user;
        $isThreadOwner = $threadOwner && $threadOwner->user_id === $user->user_id;

        // 既に要望しているかチェック
        $existingRequest = ThreadContinuationRequest::where('thread_id', $thread->thread_id)
            ->where('user_id', $user->user_id)
            ->first();

        if ($existingRequest) {
            // 要望を削除
            $existingRequest->delete();
            $action = 'removed';
        } else {
            // スレッド主以外の場合のみ、要望数が上限に達しているかチェック
            if (!$isThreadOwner) {
                $threshold = config('performance.thread.continuation_request_threshold', 3);
                $currentRequestCount = $thread->getContinuationRequestCount(); // スレッド主を除いた数
                
                if ($currentRequestCount >= $threshold) {
                    return response()->json([
                        'error' => \App\Services\LanguageService::trans('continuation_request_limit_reached', $lang)
                    ], 400);
                }
            }
            
            // 要望を追加
            ThreadContinuationRequest::create([
                'thread_id' => $thread->thread_id,
                'user_id' => $user->user_id,
            ]);
            $action = 'added';
        }

        $requestCount = $thread->getContinuationRequestCount();
        $hasUserRequest = $thread->hasContinuationRequestFromUser($user->user_id);
        
        // スレッド主の要望状態を取得
        $threadOwner = $thread->user;
        $hasOwnerRequest = false;
        if ($threadOwner) {
            $hasOwnerRequest = $thread->hasContinuationRequestFromUser($threadOwner->user_id);
        }

        // 続きスレッドが作成されたかチェック（要望追加時のみ）
        $continuationCreated = false;
        if ($action === 'added') {
            $thread->refresh();
            if ($thread->shouldCreateContinuation()) {
                $continuationThread = $this->createContinuationThread($thread);
                if ($continuationThread) {
                    $continuationCreated = true;
                    $requestCount = $thread->getContinuationRequestCount(); // 要望がクリアされたので再取得
                    $hasUserRequest = false; // 要望がクリアされたのでfalseに
                    $hasOwnerRequest = false; // 要望がクリアされたのでfalseに
                }
            }
        }

        return response()->json([
            'action' => $action,
            'request_count' => $requestCount,
            'has_user_request' => $hasUserRequest,
            'has_owner_request' => $hasOwnerRequest,
            'continuation_created' => $continuationCreated,
        ]);
    }

    /**
     * 続きスレッドを自動作成
     *
     * @param  \App\Models\Thread  $parentThread
     * @return \App\Models\Thread|null
     */
    private function createContinuationThread(Thread $parentThread)
    {
        // 既に続きスレッドが存在する場合は作成しない
        if ($parentThread->continuation_thread_id) {
            return Thread::find($parentThread->continuation_thread_id);
        }

        DB::beginTransaction();
        try {
            // タイトルから「(続き)」を削除
            $baseTitle = $parentThread->getCleanTitle();
            
            // 続きスレッドを作成（タイトルには番号を付けない。送信時言語は親スレッドを継承）
            $contSourceLang = $parentThread->source_lang ?? \App\Services\TranslationService::normalizeLang($parentThread->user->language ?? 'EN');
            $continuationThread = Thread::create([
                'title' => $baseTitle,
                'source_lang' => $contSourceLang,
                'tag' => $parentThread->tag,
                'user_id' => $parentThread->user_id,
                'responses_count' => 0,
                'access_count' => 0,
                'is_r18' => $parentThread->is_r18,
                'image_path' => $parentThread->image_path,
                'parent_thread_id' => $parentThread->thread_id,
            ]);

            // ルーム作成時に他言語へ翻訳してキャッシュに保存（一覧の言語切り替え用）
            \App\Services\TranslationService::translateAndCacheThreadTitleAtCreate(
                $continuationThread->thread_id,
                $continuationThread->title,
                $contSourceLang
            );

            // 親スレッドに続きスレッドIDを設定
            $parentThread->update([
                'continuation_thread_id' => $continuationThread->thread_id,
            ]);

            // 要望を送ったユーザーIDのリストを取得（通知送信のため）
            $requestedUserIds = ThreadContinuationRequest::where('thread_id', $parentThread->thread_id)
                ->pluck('user_id')
                ->toArray();

            // 要望をクリア（続きスレッドが作成されたので）
            ThreadContinuationRequest::where('thread_id', $parentThread->thread_id)->delete();

            DB::commit();

            // 要望を送ったユーザー全員に通知を送信
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            $threadTitle = $parentThread->getCleanTitle();
            $continuationNumber = $continuationThread->getContinuationNumber();
            
            foreach ($requestedUserIds as $userId) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $userLang = $user->language ?? $lang;
                    $title = str_replace(
                        '{n}',
                        $continuationNumber,
                        \App\Services\LanguageService::trans('continuation_thread_notification_title', $userLang)
                    );
                    $body = str_replace(
                        ['{thread_title}', '{n}'],
                        [$threadTitle, $continuationNumber],
                        \App\Services\LanguageService::trans('continuation_thread_notification_body', $userLang)
                    );
                    
                    AdminMessage::create([
                        'title' => $title,
                        'body' => $body,
                        'audience' => 'members',
                        'user_id' => $userId,
                        'published_at' => now(),
                        'allows_reply' => false,
                        'reply_used' => false,
                        'unlimited_reply' => false,
                    ]);
                }
            }

            return $continuationThread;
        } catch (\Exception $e) {
            DB::rollBack();
            \Log::error('ThreadContinuationController: Failed to create continuation thread', [
                'parent_thread_id' => $parentThread->thread_id,
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
