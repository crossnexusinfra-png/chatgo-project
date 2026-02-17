<?php

namespace App\Http\Controllers;

use App\Models\AdminMessage;
use App\Models\AdminMessageRead;
use App\Models\AdminMessageCoinReward;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;

class NotificationsController extends Controller
{
    public function index()
    {
        // 実行時間制限を一時的に延長（パフォーマンス問題の回避）
        set_time_limit(60);
        
        // 言語を最初に取得（セッションキャッシュを活用）
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        // 新しい順（上から下へ）に表示
        $query = AdminMessage::query()
            ->whereNotNull('published_at') // 送信済みのみ
            ->orderByDesc('published_at')
            ->orderByDesc('created_at');

        $userId = auth()->id();
        
        // 返信メッセージ（parent_message_idが設定されている）は除外
        $query->whereNull('parent_message_id');
        
        if ($userId) {
            // ログインユーザーの場合：個人向けメッセージまたは会員向けメッセージ
            $query->where(function($q) use ($userId) {
                $q->where('user_id', $userId) // 個人向け
                  ->orWhere(function($qq) {
                      $qq->whereNull('user_id')
                         ->where('audience', 'members'); // 会員向け（個人向けでない）
                  });
            });
        } else {
            // 非ログインユーザーは非会員向けメッセージ（個人向けでない）を表示
            $query->whereNull('user_id')
                  ->where('audience', 'guests');
        }

        // 10件ずつページネーション
        $messages = $query->paginate(10);
        
        // 各メッセージの開封状態と翻訳済みタイトル・本文を事前に計算（N+1問題の回避）
        if ($userId) {
            // ログインユーザーの場合、一度に全メッセージの開封状態とコイン受け取り状態を取得（パフォーマンス向上）
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
                
                foreach ($messages as $message) {
                    $message->is_read = in_array($message->id, $readMessageIds);
                    $message->has_received_coin = in_array($message->id, $receivedCoinMessageIds);
                    // アクセサを回避して翻訳済みタイトル・本文を事前に計算
                    $message->translated_title = $this->getTranslatedTitle($message, $lang);
                    $message->translated_body = $this->getTranslatedBody($message, $lang);
                }
            } else {
                foreach ($messages as $message) {
                    $message->is_read = false;
                    $message->has_received_coin = false;
                    // アクセサを回避して翻訳済みタイトル・本文を事前に計算
                    $message->translated_title = $this->getTranslatedTitle($message, $lang);
                    $message->translated_body = $this->getTranslatedBody($message, $lang);
                }
            }
        } else {
            // 非ログインユーザーの場合は常に未読として扱う
            foreach ($messages as $message) {
                $message->is_read = false;
                $message->has_received_coin = false;
                // アクセサを回避して翻訳済みタイトル・本文を事前に計算
                $message->translated_title = $this->getTranslatedTitle($message, $lang);
                $message->translated_body = $this->getTranslatedBody($message, $lang);
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
                    'coin_amount' => $m->coin_amount ?? null,
                    'has_received_coin' => ($m->has_received_coin ?? false),
                    'title_key' => $m->title_key ?? null,
                    'thread_id' => $m->thread_id ?? null,
                ];
            })->values();
            
            return response()->json([
                'html' => view('notifications.partials.messages', compact('messages', 'lang'))->render(),
                'hasMorePages' => $messages->hasMorePages(),
                'currentPage' => $messages->currentPage(),
                'messagesData' => $messagesData,
            ]);
        }
        
        return view('notifications.index', compact('messages', 'lang'))->with('hideSearch', true);
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
        if (!$message->unlimited_reply && $message->reply_used) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_reply_already_sent', $lang)], 403);
        }
        
        // IDOR防止: メッセージに返信する権限をチェック
        Gate::authorize('reply', $message);
        
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
            'user_id' => null, // 管理者向け（個人向けではない）
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
            'parent_message_id' => $message->id,
        ]);
        
        // 元のメッセージを返信済みにマーク（unlimited_replyがfalseの場合のみ）
        if (!$message->unlimited_reply) {
            $message->reply_used = true;
            $message->save();
        }
        
        return response()->json(['success' => true]);
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
        
        // R18変更リクエストのお知らせかチェック（Policyでチェック済みだが、念のため）
        if ($message->title_key !== 'r18_change_request_title' || !$message->thread_id) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_not_found', $lang)], 404);
        }
        
        // 既に処理済みかチェック（reply_usedがtrueの場合は既に処理済み）
        if ($message->reply_used) {
            return response()->json(['error' => \App\Services\LanguageService::trans('r18_change_already_processed', $lang)], 400);
        }
        
        try {
            // スレッドをR18に変更
            $thread = \App\Models\Thread::find($message->thread_id);
            if ($thread && !$thread->is_r18) {
                $thread->is_r18 = true;
                $thread->save();
                
                // 「成人向けコンテンツが含まれる」の通報を削除
                \App\Models\Report::where('thread_id', $thread->thread_id)
                    ->where('reason', '成人向けコンテンツが含まれる')
                    ->delete();
                
                // レスポンスの通報を削除
                $responseIds = \App\Models\Response::where('thread_id', $thread->thread_id)
                    ->pluck('response_id')
                    ->toArray();
                
                if (!empty($responseIds)) {
                    \App\Models\Report::whereIn('response_id', $responseIds)
                        ->where('reason', '成人向けコンテンツが含まれる')
                        ->delete();
                }
                
                // キャッシュをクリア
                Cache::forget('thread_restriction_' . $thread->thread_id);
            }
            
            // メッセージを処理済みにマーク
            $message->reply_used = true;
            $message->save();
            
            return response()->json(['success' => true]);
        } catch (\Exception $e) {
            \Log::error('R18変更承認に失敗', [
                'user_id' => $userId,
                'admin_message_id' => $message->id,
                'thread_id' => $message->thread_id,
                'error' => $e->getMessage()
            ]);
            return response()->json(['error' => \App\Services\LanguageService::trans('r18_change_approve_failed', $lang)], 500);
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
        
        // R18変更リクエストのお知らせかチェック（Policyでチェック済みだが、念のため）
        if ($message->title_key !== 'r18_change_request_title' || !$message->thread_id) {
            return response()->json(['error' => \App\Services\LanguageService::trans('message_not_found', $lang)], 404);
        }
        
        // 既に処理済みかチェック（reply_usedがtrueの場合は既に処理済み）
        if ($message->reply_used) {
            return response()->json(['error' => \App\Services\LanguageService::trans('r18_change_already_processed', $lang)], 400);
        }
        
        $userId = auth()->id();
        
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
    }
}


