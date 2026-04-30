<?php

namespace App\Http\Controllers;

use App\Models\AdminMessage;
use App\Models\AdminMessageRead;
use App\Models\AdminMessageCoinReward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Schema;
use App\Models\User;
use App\Models\Report;

class NotificationsController extends Controller
{
    public function index(Request $request)
    {
        // ログイン必須（ルートで auth ミドルウェア済み）。お知らせは登録日時以降のもののみ表示。
        set_time_limit(60);
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $user = auth()->user();
        $userId = $user->user_id;

        $showMandatoryFilter = Schema::hasColumn('admin_messages', 'requires_consent');
        $filter = (string) $request->query('filter', 'all');
        if (!in_array($filter, ['all', 'coin', 'mandatory'], true)) {
            $filter = 'all';
        }
        if ($filter === 'mandatory' && !$showMandatoryFilter) {
            $filter = 'all';
        }

        $query = AdminMessage::query()
            ->publishedRootForNotifications()
            ->visibleToRecipientUser($user)
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        if ($filter === 'coin') {
            $query->where('coin_amount', '>', 0);
        } elseif ($filter === 'mandatory') {
            $query->where('requires_consent', true);
        }

        $messages = $query->paginate(10)->withQueryString();
        $messageIds = $messages->pluck('id')->toArray();

        if (!empty($messageIds)) {
            $readMessageIds = AdminMessageRead::where('user_id', $userId)
                ->whereIn('admin_message_id', $messageIds)
                ->pluck('admin_message_id')
                ->toArray();
            $receivedCoinMessageIds = AdminMessageCoinReward::where('user_id', $userId)
                ->whereIn('admin_message_id', $messageIds)
                ->pluck('admin_message_id')
                ->toArray();
            $consentedMessageIds = [];
            if (Schema::hasColumn('admin_message_reads', 'consented_at')) {
                $consentedMessageIds = AdminMessageRead::where('user_id', $userId)
                    ->whereIn('admin_message_id', $messageIds)
                    ->whereNotNull('consented_at')
                    ->pluck('admin_message_id')
                    ->toArray();
            }
            foreach ($messages as $message) {
                $message->is_read = in_array($message->id, $readMessageIds);
                $message->has_received_coin = in_array($message->id, $receivedCoinMessageIds);
                $message->reply_used_by_user = false;
                if ($message->allows_reply && !$message->unlimited_reply) {
                    $message->reply_used_by_user = AdminMessage::where('parent_message_id', $message->id)
                        ->where('user_id', $userId)
                        ->exists();
                }
                $message->translated_title = $this->getTranslatedTitle($message, $lang);
                $message->translated_body = $this->getTranslatedBody($message, $lang);
                $message->report_ack_disabled = $this->isReportAckDisabledForMessage($message);
                if (Schema::hasColumn('admin_messages', 'requires_consent')) {
                    $message->requires_consent_flag = (bool) $message->getAttributeValue('requires_consent');
                    $message->has_consented = $message->requires_consent_flag
                        && in_array($message->id, $consentedMessageIds, true);
                } else {
                    $message->requires_consent_flag = false;
                    $message->has_consented = false;
                }
            }
        } else {
            foreach ($messages as $message) {
                $message->is_read = false;
                $message->has_received_coin = false;
                $message->reply_used_by_user = false;
                $message->translated_title = $this->getTranslatedTitle($message, $lang);
                $message->translated_body = $this->getTranslatedBody($message, $lang);
                $message->report_ack_disabled = $this->isReportAckDisabledForMessage($message);
                $message->requires_consent_flag = false;
                $message->has_consented = false;
            }
        }

        // AJAXリクエストの場合はJSON形式で返す
        if (request()->ajax() || request()->wantsJson()) {
            $messagesData = $messages->map(function($m) {
                return [
                    'id' => $m->id,
                    'title' => $m->translated_title ?? $m->title ?? '',
                    'body' => $m->translated_body ?? $m->body ?? '',
                    'is_read' => ($m->is_read ?? false),
                    'allows_reply' => ($m->allows_reply ?? false),
                    'unlimited_reply' => ($m->unlimited_reply ?? false),
                    'reply_used' => ($m->reply_used ?? false),
                    'reply_used_by_user' => ($m->reply_used_by_user ?? false),
                    'coin_amount' => $m->coin_amount ?? null,
                    'has_received_coin' => ($m->has_received_coin ?? false),
                    'title_key' => $m->title_key ?? null,
                    'thread_id' => $m->thread_id ?? null,
                    'report_ack_disabled' => ($m->report_ack_disabled ?? false),
                    'requires_consent' => ($m->requires_consent_flag ?? false),
                    'has_consented' => ($m->has_consented ?? false),
                ];
            })->values();
            
            return response()->json([
                'html' => view('notifications.partials.messages', compact('messages', 'lang'))->render(),
                'hasMorePages' => $messages->hasMorePages(),
                'currentPage' => $messages->currentPage(),
                'messagesData' => $messagesData,
                'filter' => $filter,
            ]);
        }
        
        return view('notifications.index', compact('messages', 'lang', 'filter', 'showMandatoryFilter'))->with('hideSearch', true);
    }

    /**
     * メッセージを開封済みにする
     */
    public function markAsRead(Request $request, $message)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        if (!auth()->check()) {
            return response()->json(['error' => \App\Services\LanguageService::trans('login_required_error', $lang)], 401);
        }

        // メッセージIDからモデルを取得
        $adminMessage = AdminMessage::find($message);
        
        if (!$adminMessage) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_not_found', $lang)], 404);
        }
        
        // IDOR防止: メッセージを開封済みにする権限をチェック
        Gate::authorize('markAsRead', $adminMessage);
        
        $userId = auth()->id();

        // 既に開封済みの場合は何もしない
        if (!$adminMessage->isReadBy($userId)) {
            try {
                AdminMessageRead::create([
                    'user_id' => $userId,
                    'admin_message_id' => $adminMessage->id,
                    'read_at' => now(),
                ]);
            } catch (\Exception $e) {
                // 重複エラーなどは無視（既に開封済みとして扱う）
                \Log::warning('AdminMessageReadの作成に失敗', [
                    'user_id' => $userId,
                    'admin_message_id' => $adminMessage->id,
                    'error' => $e->getMessage()
                ]);
            }
        }

        return response()->json(['success' => true]);
    }

    /**
     * 同意必須お知らせに同意する（利用制限解除）
     */
    public function consentMandatory(Request $request, $message)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        if (!auth()->check()) {
            return response()->json(['error' => \App\Services\LanguageService::trans('login_required_error', $lang)], 401);
        }

        $adminMessage = AdminMessage::find($message);
        if (!$adminMessage) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_not_found', $lang)], 404);
        }

        Gate::authorize('consentMandatoryNotice', $adminMessage);

        if (!Schema::hasColumn('admin_messages', 'requires_consent')
            || !Schema::hasColumn('admin_message_reads', 'consented_at')
            || !$adminMessage->getAttributeValue('requires_consent')) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_not_found', $lang)], 400);
        }

        $userId = auth()->id();

        $lock = \App\Services\DuplicateSubmissionLockService::acquire('notifications.mandatory-consent', $userId, (string) $adminMessage->id);
        if (!$lock) {
            return response()->json(['error' => \App\Services\LanguageService::trans('duplicate_submission', $lang)], 429);
        }
        try {
            $read = AdminMessageRead::firstOrNew([
                'user_id' => $userId,
                'admin_message_id' => $adminMessage->id,
            ]);
            if (!$read->exists) {
                $read->read_at = now();
            }
            $read->consented_at = now();
            $read->save();

            return response()->json(['success' => true]);
        } finally {
            $lock->release();
        }
    }

    /**
     * メッセージに返信する（拒否時のメッセージのみ、一度限り）
     */
    public function reply(Request $request, AdminMessage $message)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $userId = auth()->id();
        
        if (!$userId) {
            return response()->json(['error' => \App\Services\LanguageService::trans('login_required_error', $lang)], 401);
        }
        
        // 返信可能かチェック
        if (!$message->allows_reply) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_reply_not_allowed', $lang)], 403);
        }
        
        // 既に返信済みかチェック（unlimited_replyがfalseの場合のみ）
        if (!$message->unlimited_reply) {
            $alreadyRepliedByUser = AdminMessage::where('parent_message_id', $message->id)
                ->where('user_id', $userId)
                ->exists();
            if ($alreadyRepliedByUser) {
                return response()->json(['error' => \App\Services\LanguageService::trans('message_reply_already_sent', $lang)], 403);
            }
        }
        
        // IDOR防止: メッセージに返信する権限をチェック
        Gate::authorize('reply', $message);

        // 重複実行防止
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('notifications.reply', $userId, (string) $message->id);
        if (!$lock) {
            return response()->json(['error' => \App\Services\LanguageService::trans('duplicate_submission', $lang)], 429);
        }
        try {

        $request->validate([
            'body' => 'required|string|max:2000',
        ]);

        // お知らせ返信本文: HTMLタグを除去して保存（XSS等の防御）
        $body = mb_substr(strip_tags($request->body), 0, 2000);
        
        // R18変更のお知らせの場合は返信を許可しない
        if ($message->title_key === 'r18_change_request_title') {
            return response()->json(['error' => \App\Services\LanguageService::trans('r18_change_use_buttons', $lang)], 403);
        }
        
        // 返信メッセージを作成
        // 親メッセージのタイトルを取得（アクセサメソッドを使用）
        $parentTitle = $message->title ?? '';
        
        AdminMessage::create([
            'title' => 'Re: ' . $parentTitle,
            'body' => $body,
            'audience' => 'members',
            // 返信したユーザーを保持（管理者側の返信一覧で識別するため）
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
            'parent_message_id' => $message->id,
            'is_auto_sent' => false,
        ]);
        
        return response()->json(['success' => true]);
        } finally {
            $lock->release();
        }
    }

    /**
     * メッセージの翻訳済みタイトルを取得（アクセサを回避）
     */
    private function getTranslatedTitle($message, $lang): ?string
    {
        $titleKey = $message->getAttributeValue('title_key');
        if ($titleKey) {
            return \App\Services\LanguageService::trans($titleKey, $lang);
        }
        if (strtoupper((string) $lang) === 'EN') {
            return $message->getAttributeValue('title_en')
                ?? $message->getAttributeValue('title_ja')
                ?? $message->getAttributeValue('title');
        }
        if (strtoupper((string) $lang) === 'JA') {
            return $message->getAttributeValue('title_ja')
                ?? $message->getAttributeValue('title_en')
                ?? $message->getAttributeValue('title');
        }
        return $message->getAttributeValue('title');
    }

    /**
     * メッセージの翻訳済み本文を取得（アクセサを回避）
     */
    private function getTranslatedBody($message, $lang): string
    {
        $bodyKey = $message->getAttributeValue('body_key');
        if ($bodyKey) {
            return \App\Services\LanguageService::trans($bodyKey, $lang);
        }
        if (strtoupper((string) $lang) === 'EN') {
            return $message->getAttributeValue('body_en')
                ?? $message->getAttributeValue('body_ja')
                ?? ($message->getAttributeValue('body') ?? '');
        }
        if (strtoupper((string) $lang) === 'JA') {
            return $message->getAttributeValue('body_ja')
                ?? $message->getAttributeValue('body_en')
                ?? ($message->getAttributeValue('body') ?? '');
        }
        return $message->getAttributeValue('body') ?? '';
    }

    /**
     * メッセージからコインを受け取る
     */
    public function receiveCoin(Request $request, AdminMessage $message)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $userId = auth()->id();
        
        if (!$userId) {
            return response()->json(['error' => \App\Services\LanguageService::trans('login_required_error', $lang)], 401);
        }

        // コインが付与されていない場合はエラー
        if (!$message->coin_amount || $message->coin_amount <= 0) {
            return response()->json(['error' => \App\Services\LanguageService::trans('notification_coin_receive_failed', $lang)], 400);
        }

        // IDOR防止: メッセージからコインを受け取る権限をチェック
        Gate::authorize('receiveCoin', $message);

        // 重複実行防止
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('notifications.receive-coin', $userId, (string) $message->id);
        if (!$lock) {
            return response()->json(['error' => \App\Services\LanguageService::trans('duplicate_submission', $lang)], 429);
        }
        try {

        // 既にコインを受け取っているかチェック
        if ($message->hasReceivedCoin($userId)) {
            return response()->json(['error' => \App\Services\LanguageService::trans('notification_coin_already_received', $lang)], 400);
        }

        try {
            // コインを受け取る記録を保存
            AdminMessageCoinReward::create([
                'user_id' => $userId,
                'admin_message_id' => $message->id,
                'coin_amount' => $message->coin_amount,
                'received_at' => now(),
            ]);

            // ユーザーにコインを追加
            $user = \App\Models\User::find($userId);
            if ($user) {
                $coinService = new \App\Services\CoinService();
                $coinService->addCoins($user, $message->coin_amount);
            }

            return response()->json([
                'success' => true,
                'coins' => $message->coin_amount,
            ]);
        } catch (\Exception $e) {
            \Log::error('コイン受け取りに失敗', [
                'user_id' => $userId,
                'admin_message_id' => $message->id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => \App\Services\LanguageService::trans('notification_coin_receive_failed', $lang)], 500);
        }
        } finally {
            $lock->release();
        }
    }

    /**
     * R18変更リクエストを承認する
     */
    public function approveR18Change(Request $request, AdminMessage $message)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $userId = auth()->id();
        
        if (!$userId) {
            return response()->json(['error' => \App\Services\LanguageService::trans('login_required_error', $lang)], 401);
        }
        
        // IDOR防止: R18変更リクエストを承認する権限をチェック
        Gate::authorize('approveR18Change', $message);

        // 重複実行防止
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('notifications.r18-approve', $userId, (string) $message->id);
        if (!$lock) {
            return response()->json(['error' => \App\Services\LanguageService::trans('duplicate_submission', $lang)], 429);
        }
        try {

        // R18変更リクエストのお知らせかチェック（Policyでチェック済みだが、念のため）
        if ($message->title_key !== 'r18_change_request_title' || !$message->thread_id) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_not_found', $lang)], 404);
        }

        $threadId = (int) $message->thread_id;
        $reportRestrictionTitleKeys = ['report_restriction_review_title', 'report_restriction_ack_title'];
        $reportMessagesToEnable = [];
        $reportMessagesToDisable = [];
        
        // 既に処理済みかチェック（reply_usedがtrueの場合は既に処理済み）
        if ($message->reply_used) {
            return response()->json(['error' => \App\Services\LanguageService::trans('r18_change_already_processed', $lang)], 400);
        }
        
        try {
            // スキーマ差分（deleted_at未反映）でも落ちないように取得方法を切り替える
            $canUseSoftDeletes = false;
            try {
                $canUseSoftDeletes = \Illuminate\Support\Facades\Schema::hasColumn('threads', 'deleted_at');
            } catch (\Throwable $e) {
                $canUseSoftDeletes = false;
            }
            // スレッドをR18に変更
            $thread = $canUseSoftDeletes
                ? \App\Models\Thread::withTrashed()->find($threadId)
                : \App\Models\Thread::find($threadId);
            if ($thread && !$thread->is_r18) {
                // 取り消し通知のため、削除対象となる通報者を事前に収集
                $responseIds = \App\Models\Response::where('thread_id', $threadId)
                    ->pluck('response_id')
                    ->toArray();

                // R18切替後は「成人向けコンテンツが含まれる」理由の通報了承のみ不可にする
                try {
                    $threadReportMessages = AdminMessage::whereIn('title_key', $reportRestrictionTitleKeys)
                        ->where('thread_id', $threadId)
                        ->get();

                    $responseReportMessages = !empty($responseIds)
                        ? AdminMessage::whereIn('title_key', $reportRestrictionTitleKeys)
                            ->whereIn('response_id', $responseIds)
                            ->get()
                        : collect();

                    $reportMessagesToEnable = [];
                    $reportMessagesToDisable = $threadReportMessages
                        ->merge($responseReportMessages)
                        ->filter(fn ($msg) => $this->hasAdultContentReasonPendingForMessage($msg))
                        ->pluck('id')
                        ->unique()
                        ->values()
                        ->all();
                } catch (\Throwable $e) {
                    \Log::warning('R18 approve: report button control failed (ignored)', [
                        'user_id' => $userId,
                        'admin_message_id' => $message->id,
                        'thread_id' => $threadId,
                        'error' => $e->getMessage(),
                    ]);
                    $reportMessagesToEnable = [];
                    $reportMessagesToDisable = [];
                }

                try {
                    if (!empty($reportMessagesToDisable)) {
                        AdminMessage::whereIn('id', $reportMessagesToDisable)
                            ->update(['reply_used' => true]);
                    }
                } catch (\Throwable $e) {
                    \Log::warning('R18 approve: failed to close report ack buttons (ignored)', [
                        'user_id' => $userId,
                        'admin_message_id' => $message->id,
                        'thread_id' => $threadId,
                        'error' => $e->getMessage(),
                    ]);
                }

                $cancelledReporterIds = \App\Models\Report::where(function ($q) use ($threadId, $responseIds) {
                        $q->where('thread_id', $threadId);
                        if (!empty($responseIds)) {
                            $q->orWhereIn('response_id', $responseIds);
                        }
                    })
                    ->where('reason', '成人向けコンテンツが含まれる')
                    ->whereNotNull('user_id')
                    ->pluck('user_id')
                    ->unique()
                    ->values()
                    ->toArray();

                // ソフトデリートの restore + is_r18 更新を Eloquent に依存せず一発で反映（イベント差分でも落ちにくくする）
                try {
                    $threadPatch = [
                        'is_r18' => true,
                        'updated_at' => now(),
                    ];
                    if ($canUseSoftDeletes) {
                        $threadPatch['deleted_at'] = null;
                    }
                    DB::table('threads')->where('thread_id', $threadId)->update($threadPatch);
                } catch (\Throwable $e) {
                    \Log::warning('R18 approve: thread DB::update failed, trying Eloquent withoutEvents', [
                        'user_id' => $userId,
                        'admin_message_id' => $message->id,
                        'thread_id' => $threadId,
                        'error' => $e->getMessage(),
                    ]);
                    \App\Models\Thread::withoutEvents(function () use ($thread, $canUseSoftDeletes) {
                        if ($canUseSoftDeletes && method_exists($thread, 'trashed') && $thread->trashed()) {
                            $thread->restore();
                        }
                        $thread->is_r18 = true;
                        $thread->save();
                    });
                }
                
                // 「成人向けコンテンツが含まれる」の通報を削除
                try {
                    \App\Models\Report::where('thread_id', $thread->thread_id)
                        ->where('reason', '成人向けコンテンツが含まれる')
                        ->delete();
                } catch (\Throwable $e) {
                    \Log::warning('R18 approve: deleting thread adult reports failed (ignored)', [
                        'user_id' => $userId,
                        'admin_message_id' => $message->id,
                        'thread_id' => $threadId,
                        'error' => $e->getMessage(),
                    ]);
                }
                
                // レスポンスの通報を削除
                if (!empty($responseIds)) {
                    try {
                        \App\Models\Report::whereIn('response_id', $responseIds)
                            ->where('reason', '成人向けコンテンツが含まれる')
                            ->delete();
                    } catch (\Throwable $e) {
                        \Log::warning('R18 approve: deleting response adult reports failed (ignored)', [
                            'user_id' => $userId,
                            'admin_message_id' => $message->id,
                            'thread_id' => $threadId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }

                // R18変更で成人向け通報を取り消した場合は、通報による制限状態も解除する
                try {
                    if (Schema::hasTable('report_restrictions')) {
                        $restrictionUpdate = [
                            'status' => 'cleared',
                        ];
                        if (Schema::hasColumn('report_restrictions', 'acknowledged_at')) {
                            $restrictionUpdate['acknowledged_at'] = now();
                        }
                        if (Schema::hasColumn('report_restrictions', 'updated_at')) {
                            $restrictionUpdate['updated_at'] = now();
                        }

                        // ルーム通報由来の制限
                        DB::table('report_restrictions')
                            ->where('status', 'active')
                            ->where('type', 'thread')
                            ->where('thread_id', $threadId)
                            ->update($restrictionUpdate);

                        // リプライ通報由来の制限
                        if (!empty($responseIds)) {
                            DB::table('report_restrictions')
                                ->where('status', 'active')
                                ->where('type', 'response')
                                ->whereIn('response_id', $responseIds)
                                ->update($restrictionUpdate);
                        }
                    }
                } catch (\Throwable $e) {
                    \Log::warning('R18 approve: clearing report restrictions failed (ignored)', [
                        'user_id' => $userId,
                        'admin_message_id' => $message->id,
                        'thread_id' => $threadId,
                        'error' => $e->getMessage(),
                    ]);
                }

                // 通報が取り消された旨を通報者へ通知
                if (!empty($cancelledReporterIds)) {
                    try {
                        $this->notifyAdultContentReportCancellation($thread, $cancelledReporterIds);
                    } catch (\Throwable $e) {
                        // 取り消し通知の失敗でR18変更本体を失敗させない
                        \Log::warning('R18 approve: cancellation notice failed (ignored)', [
                            'user_id' => $userId,
                            'admin_message_id' => $message->id,
                            'thread_id' => $threadId,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
                
                // キャッシュをクリア
                try {
                    Cache::forget('thread_restriction_' . $thread->thread_id);
                } catch (\Throwable $e) {
                    \Log::warning('R18 approve: cache forget failed (ignored)', [
                        'user_id' => $userId,
                        'admin_message_id' => $message->id,
                        'thread_id' => $threadId,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
            
            // メッセージを処理済みにマーク
            try {
                $message->reply_used = true;
                $message->save();
            } catch (\Throwable $e) {
                \Log::warning('R18 approve: admin_message Eloquent save failed, DB fallback', [
                    'user_id' => $userId,
                    'admin_message_id' => $message->id,
                    'error' => $e->getMessage(),
                ]);
                $fallback = ['reply_used' => true];
                try {
                    if (Schema::hasColumn('admin_messages', 'updated_at')) {
                        $fallback['updated_at'] = now();
                    }
                } catch (\Throwable $ignored) {
                }
                DB::table('admin_messages')->where('id', $message->id)->update($fallback);
            }
            
            return response()->json([
                'success' => true,
                'reportMessagesToEnable' => $reportMessagesToEnable,
                'reportMessagesToDisable' => $reportMessagesToDisable,
            ]);
        } catch (\Throwable $e) {
            $errorId = (string) \Illuminate\Support\Str::uuid();
            \Log::error('R18変更承認に失敗', [
                'error_id' => $errorId,
                'user_id' => $userId,
                'admin_message_id' => $message->id,
                'thread_id' => $message->thread_id,
                'error' => $e->getMessage(),
                'exception' => $e,
            ]);
            return response()->json([
                'error' => \App\Services\LanguageService::trans('r18_change_approve_failed', $lang) . " (error_id: {$errorId})",
            ], 500);
        }
        } finally {
            $lock->release();
        }
    }

    /**
     * R18変更により取り消された通報について、通報者へ通知する
     *
     * @param \App\Models\Thread $thread
     * @param array<int> $reporterIds
     * @return void
     */
    private function notifyAdultContentReportCancellation(\App\Models\Thread $thread, array $reporterIds): void
    {
        foreach ($reporterIds as $reporterId) {
            $user = User::where('user_id', $reporterId)->first();
            $isEn = strtoupper((string) ($user?->language ?? 'JA')) === 'EN';
            $targetLang = $isEn ? 'EN' : 'JA';
            $sourceLang = \App\Services\TranslationService::normalizeLang((string) ($thread->source_lang ?? ($thread->user->language ?? 'JA')));
            $threadTitle = \App\Services\TranslationService::getTranslatedThreadTitle((int) $thread->thread_id, (string) $thread->title, $targetLang, $sourceLang, false);
            AdminMessage::create([
                'title' => $isEn ? 'Update on Your Report' : '通報内容の対応について',
                'body' => $isEn
                    ? "Your report for \"Contains adult content\" has been withdrawn because the room \"{$threadTitle}\" was changed to R18."
                    : "あなたが「成人向けコンテンツが含まれる」で通報した内容は、ルーム「{$threadTitle}」がR18ルームへ変更されたため取り下げられました。",
                'audience' => 'members',
                'user_id' => $reporterId,
                'thread_id' => $thread->thread_id,
                'published_at' => now(),
                'allows_reply' => false,
                'reply_used' => false,
                'unlimited_reply' => false,
                'is_auto_sent' => true,
            ]);
        }
    }

    /**
     * R18変更リクエストを拒否する
     */
    public function rejectR18Change(Request $request, AdminMessage $message)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $userId = auth()->id();
        
        if (!$userId) {
            return response()->json(['error' => \App\Services\LanguageService::trans('login_required_error', $lang)], 401);
        }
        
        // IDOR防止: R18変更リクエストを拒否する権限をチェック
        Gate::authorize('rejectR18Change', $message);

        // 重複実行防止
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('notifications.r18-reject', $userId, (string) $message->id);
        if (!$lock) {
            return response()->json(['error' => \App\Services\LanguageService::trans('duplicate_submission', $lang)], 429);
        }
        try {

        // R18変更リクエストのお知らせかチェック（Policyでチェック済みだが、念のため）
        if ($message->title_key !== 'r18_change_request_title' || !$message->thread_id) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_not_found', $lang)], 404);
        }
        
        // 既に処理済みかチェック（reply_usedがtrueの場合は既に処理済み）
        if ($message->reply_used) {
            return response()->json(['error' => \App\Services\LanguageService::trans('r18_change_already_processed', $lang)], 400);
        }
        
        try {
            // メッセージを処理済みにマーク
            $message->reply_used = true;
            $message->save();
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('R18変更拒否に失敗', [
                'user_id' => $userId,
                'admin_message_id' => $message->id,
                'thread_id' => $message->thread_id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => \App\Services\LanguageService::trans('r18_change_reject_failed', $lang)], 500);
        }
        } finally {
            $lock->release();
        }
    }

    /**
     * 通報制限の了承（自認）を実行する
     */
    public function acknowledgeReportRestriction(Request $request, AdminMessage $message)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $userId = auth()->id();
        $errorId = (string) \Illuminate\Support\Str::uuid();

        if (!$userId) {
            return response()->json(['error' => \App\Services\LanguageService::trans('login_required_error', $lang)], 401);
        }

        // IDOR防止: 対象メッセージに対する権限チェック
        \Illuminate\Support\Facades\Gate::authorize('acknowledgeReportRestriction', $message);

        // 重複実行防止
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('notifications.report-acknowledge', $userId, (string) $message->id);
        if (!$lock) {
            return response()->json(['error' => \App\Services\LanguageService::trans('duplicate_submission', $lang)], 429);
        }
        try {
            $isReportRestrictionReview = in_array($message->title_key, ['report_restriction_review_title', 'report_restriction_ack_title'], true);
            if (!$isReportRestrictionReview) {
                return response()->json(['error' => \App\Services\LanguageService::trans('message_not_found', $lang)], 404);
            }
            if ($message->reply_used) {
                return response()->json(['error' => \App\Services\LanguageService::trans('r18_change_already_processed', $lang)], 400);
            }

            // R18ルームに変更済みでも「成人向けコンテンツが含まれる」理由の通報了承のみ不可
            $targetThread = null;
            if ($message->thread_id) {
                $targetThread = \App\Models\Thread::withTrashed()->find((int) $message->thread_id);
            } elseif ($message->response_id) {
                $response = \App\Models\Response::find((int) $message->response_id);
                if ($response && $response->thread_id) {
                    $targetThread = \App\Models\Thread::withTrashed()->find((int) $response->thread_id);
                }
            }

            if (
                $targetThread
                && $targetThread->is_r18
                && $this->hasAdultContentReasonPendingForMessage($message)
            ) {
                return response()->json([
                    'error' => \App\Services\LanguageService::trans('report_ack_disabled_after_r18_change', $lang),
                ], 403);
            }

            try {
                $service = app(\App\Services\ReportRestrictionService::class);
                $result = $service->acknowledgeFromMessage($message);
                return response()->json(['success' => true, 'result' => $result]);
            } catch (\Throwable $e) {
                $step = null;
                $msg = (string) $e->getMessage();
                if (str_starts_with($msg, '[ACK_STEP]')) {
                    $step = trim(substr($msg, strlen('[ACK_STEP]')));
                }
                if ($step === 'r18_ack_not_allowed') {
                    return response()->json([
                        'error' => \App\Services\LanguageService::trans('report_ack_disabled_after_r18_change', $lang),
                    ], 403);
                }
                if ($step === 'already_moderated') {
                    return response()->json([
                        'error' => \App\Services\LanguageService::trans('report_ack_disabled_after_admin_moderated', $lang),
                    ], 403);
                }
                \Log::error('Report restriction acknowledge failed', [
                    'error_id' => $errorId,
                    'user_id' => $userId,
                    'admin_message_id' => $message->id,
                    'error' => $e->getMessage(),
                    'exception' => $e,
                    'title_key' => $message->title_key,
                    'thread_id' => $message->thread_id,
                    'response_id' => $message->response_id,
                ]);
                return response()->json([
                    'error' => \App\Services\LanguageService::trans('report_restriction_ack_failed', $lang)
                        . " (error_id: {$errorId}" . ($step ? ", step: {$step}" : '') . ")",
                ], 500);
            }
        } finally {
            $lock->release();
        }
    }

    /**
     * 対象メッセージに、R18変更提案に相当する未処理通報理由が残っているか判定
     */
    private function hasAdultContentReasonPendingForMessage(AdminMessage $message): bool
    {
        $reason = '成人向けコンテンツが含まれる';
        if ($message->response_id) {
            return \App\Models\Report::where('response_id', (int) $message->response_id)
                ->where('reason', $reason)
                ->whereNull('approved_at')
                ->exists();
        }
        if ($message->thread_id) {
            return \App\Models\Report::where('thread_id', (int) $message->thread_id)
                ->where('reason', $reason)
                ->whereNull('approved_at')
                ->exists();
        }
        return false;
    }

    /**
     * 通報了承ボタンを初期表示で無効化すべきか判定
     */
    private function isReportAckDisabledForMessage(AdminMessage $message): bool
    {
        $isReportRestrictionReview = in_array($message->title_key, ['report_restriction_review_title', 'report_restriction_ack_title'], true);
        if (!$isReportRestrictionReview || $message->reply_used) {
            return false;
        }

        // R18ルーム変更済みかつ成人向け理由が残っている場合は不可
        $targetThread = null;
        if ($message->thread_id) {
            $targetThread = \App\Models\Thread::withTrashed()->find((int) $message->thread_id);
        } elseif ($message->response_id) {
            $response = \App\Models\Response::find((int) $message->response_id);
            if ($response && $response->thread_id) {
                $targetThread = \App\Models\Thread::withTrashed()->find((int) $response->thread_id);
            }
        }
        if ($targetThread && $targetThread->is_r18 && $this->hasAdultContentReasonPendingForMessage($message)) {
            return true;
        }

        // 管理者側で承認/拒否済み（未処理が無い）なら不可
        $base = null;
        if ($message->response_id) {
            $base = Report::where('response_id', (int) $message->response_id);
        } elseif ($message->thread_id) {
            $base = Report::where('thread_id', (int) $message->thread_id);
        }
        if (!$base) {
            return false;
        }

        $pending = (clone $base)->whereNull('approved_at')->count();
        $processed = (clone $base)->whereNotNull('approved_at')->count();
        return $pending === 0 && $processed > 0;
    }
}


