<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Thread;
use App\Models\Response;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    /**
     * 既存の通報情報を取得する（API）
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getExisting(Request $request)
    {
        // AJAXリクエストでない場合はトップページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->route('threads.index');
        }
        
        if (!Auth::check()) {
            return response()->json(['error' => 'Unauthorized'], 401);
        }

        $threadId = $request->input('thread_id');
        $responseId = $request->input('response_id');
        $reportedUserId = null; // プロフィール通報は廃止
        $userId = Auth::id();

        $report = null;
        $thread = null;
        
        if ($threadId) {
            $thread = Thread::find($threadId);
            $report = Report::where('user_id', $userId)
                ->where('thread_id', $threadId)
                ->first();
        } elseif ($responseId) {
            $response = Response::find($responseId);
            if ($response) {
                $thread = $response->thread;
            }
            $report = Report::where('user_id', $userId)
                ->where('response_id', $responseId)
                ->first();
        }
        
        // プロフィール通報の場合はis_r18_threadは不要
        $isR18Thread = false;
        
        // IDOR防止: 自分の通報のみ表示可能
        if ($report) {
            \Illuminate\Support\Facades\Gate::authorize('view', $report);
        }

        // R18タグ（3種類）を定義
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        
        // スレッドがR18かどうかを判定（スレッド通報の場合のみ）
        if ($thread) {
            $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
        }

        if ($report) {
            return response()->json([
                'exists' => true,
                'reason' => $report->reason,
                'description' => $report->description,
                'is_r18_thread' => $isR18Thread,
            ]);
        }

        return response()->json([
            'exists' => false,
            'is_r18_thread' => $isR18Thread,
        ]);
    }

    /**
     * スレッドまたはレスポンスを通報する
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        // ログインチェック
        if (!Auth::check()) {
            return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_login_required', $lang)]);
        }
        
        // IDOR防止: 通報を作成する権限をチェック
        \Illuminate\Support\Facades\Gate::authorize('create', Report::class);

        // 重複実行防止
        $userId = Auth::id();
        $resourceId = $request->input('thread_id') ?: $request->input('response_id');
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('report.store', $userId, $resourceId ? (string) $resourceId : null);
        if (!$lock) {
            return back()->withErrors(['report' => \App\Services\LanguageService::trans('duplicate_submission', $lang)]);
        }
        try {

        // バリデーション
        $validated = $request->validate([
            'thread_id' => 'nullable|exists:threads,thread_id',
            'response_id' => 'nullable|exists:responses,response_id',
            'reason' => 'required|string|in:スパム・迷惑行為,攻撃的・不適切な内容,不適切なリンク・外部誘導,成人向けコンテンツが含まれる,成人向け以外のコンテンツ規制違反,異なる思想に関しての意見の押し付け、妨害,スレッド画像が第三者の著作権を侵害している可能性がある,スレッド画像に個人情報・他人の情報が含まれている,スレッド画像に不適切な内容が含まれている,ルーム画像が第三者の著作権を侵害している可能性がある,ルーム画像に個人情報・他人の情報が含まれている,ルーム画像に不適切な内容が含まれている,なりすまし・虚偽の人物情報,その他',
            'description' => 'nullable|string|max:300',
        ]);

        // スレッド、レスポンスのいずれか一方が必須（プロフィール通報は廃止）
        $targetCount = 0;
        if ($validated['thread_id']) $targetCount++;
        if ($validated['response_id']) $targetCount++;
        
        if ($targetCount === 0) {
            return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_thread_or_response_required', $lang)]);
        }

        // 複数が指定されている場合はエラー
        if ($targetCount > 1) {
            return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_both_not_allowed', $lang)]);
        }

        // スレッド画像関連の通報理由はスレッド通報時のみ許可
        $threadImageReasons = [
            'スレッド画像が第三者の著作権を侵害している可能性がある',
            'スレッド画像に個人情報・他人の情報が含まれている',
            'スレッド画像に不適切な内容が含まれている',
            'ルーム画像が第三者の著作権を侵害している可能性がある',
            'ルーム画像に個人情報・他人の情報が含まれている',
            'ルーム画像に不適切な内容が含まれている',
        ];
        if (in_array($validated['reason'], $threadImageReasons) && !$validated['thread_id']) {
            return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_thread_image_reason_thread_only', $lang)]);
        }

        // 通報数上限チェック（未処理の通報数が10件以上の場合、通報を拒否）
        $pendingReportCount = Report::where('user_id', $userId)
            ->whereNull('approved_at')
            ->count();
        
        if ($pendingReportCount >= 10) {
            return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_limit_exceeded', $lang)]);
        }

        // R18タグ（3種類）を定義
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];

        $thread = null;
        $response = null;
        $reportedUser = null;

        // スレッドを通報する場合の処理
        if ($validated['thread_id']) {
            $thread = Thread::findOrFail($validated['thread_id']);
            $thread->loadMissing('user');
            if ($thread->user && !empty($thread->user->is_admin)) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_cannot_report_admin_content', $lang)]);
            }

            // 自分のルームは通報できない
            if ((int) $thread->user_id === (int) $userId) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_cannot_report_own_thread', $lang)]);
            }

            // ルーム画像関連の通報理由は、サンプル画像（image_path 未設定）では選択不可
            if (in_array($validated['reason'], $threadImageReasons, true) && empty($thread->image_path)) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_thread_image_reason_thread_only', $lang)]);
            }
            
            // 警告状態（制限がかかっている）の場合、通報を拒否
            if ($thread->isRestricted()) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_restricted_thread', $lang)]);
            }
            
            // 承認または拒否済みのスレッドを通報できない
            $hasProcessedReport = Report::where('thread_id', $validated['thread_id'])
                ->whereNotNull('approved_at')
                ->exists();
            
            if ($hasProcessedReport) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_already_processed', $lang)]);
            }
            
            // R18スレッドまたはR18タグのスレッドの場合、「成人向け以外のコンテンツ規制違反」を通報できない
            if (($thread->is_r18 || in_array($thread->tag, $r18Tags)) && $validated['reason'] === '成人向け以外のコンテンツ規制違反') {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('r18_thread_cannot_report_content_violation', $lang)]);
            }
            
        } elseif ($validated['response_id']) {
            $response = Response::findOrFail($validated['response_id']);
            $response->loadMissing('user');
            if ($response->user && !empty($response->user->is_admin)) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_cannot_report_admin_content', $lang)]);
            }

            // 自分のリプライは通報できない
            if ((int) $response->user_id === (int) $userId) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_cannot_report_own_response', $lang)]);
            }
            
            // 警告状態（制限がかかっている）の場合、通報を拒否
            if ($response->shouldBeHidden()) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_restricted_response', $lang)]);
            }
            
            // 承認または拒否済みのレスポンスを通報できない
            $hasProcessedReport = Report::where('response_id', $validated['response_id'])
                ->whereNotNull('approved_at')
                ->exists();
            
            if ($hasProcessedReport) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_already_processed', $lang)]);
            }
        }

        // 既存の通報を確認（同じユーザーが同じスレッド/レスポンス/プロフィールを通報済みか）
        $existingReport = null;
        if ($validated['thread_id']) {
            $existingReport = Report::where('user_id', $userId)
                ->where('thread_id', $validated['thread_id'])
                ->first();
        } elseif ($validated['response_id']) {
            $existingReport = Report::where('user_id', $userId)
                ->where('response_id', $validated['response_id'])
                ->first();
        }

        // 自由入力の通報理由（description）: HTMLタグを除去して保存（XSS等の防御）
        $description = isset($validated['description']) && $validated['description'] !== ''
            ? mb_substr(strip_tags($validated['description']), 0, 300)
            : null;

        // 既存の通報がある場合
        if ($existingReport) {
            // 承認または拒否済みの通報の場合は再通報を拒否
            if ($existingReport->approved_at) {
                return back()->withErrors(['report' => \App\Services\LanguageService::trans('report_already_processed', $lang)]);
            }
            
            // 未処理の通報の場合は更新可能
            $existingReport->update([
                'reason' => $validated['reason'],
                'description' => $description,
            ]);
            $message = \App\Services\LanguageService::trans('report_updated', $lang);
        } else {
            Report::create([
                'user_id' => $userId,
                'thread_id' => $validated['thread_id'] ?? null,
                'response_id' => $validated['response_id'] ?? null,
                'reason' => $validated['reason'],
                'description' => $description,
            ]);
            $message = \App\Services\LanguageService::trans('report_submitted', $lang);
        }

        // 通報により制限が発生した場合、対象ユーザーに了承ボタン付き通知を送る（初回のみ）
        try {
            $restrictionService = app(\App\Services\ReportRestrictionService::class);
            if (!empty($validated['thread_id'])) {
                $t = Thread::find($validated['thread_id']);
                if ($t) {
                    $restrictionService->ensureRestrictionCreatedForThread($t);
                    $this->sendR18ChangeNotificationIfNeeded($t);
                }
            } elseif (!empty($validated['response_id'])) {
                $r = Response::find($validated['response_id']);
                if ($r) {
                    $restrictionService->ensureRestrictionCreatedForResponse($r);
                    if ($r->thread) {
                        $this->sendR18ChangeNotificationIfNeeded($r->thread, $r);
                    }
                }
            }
        } catch (\Throwable $e) {
            \Log::warning('Failed to create report restriction notification', [
                'error' => $e->getMessage(),
            ]);
        }

        return back()->with('success', $message);
        } finally {
            $lock->release();
        }
    }

    /**
     * 「成人向けコンテンツが含まれる」で制限がかかった場合、R18変更のお知らせを送信
     */
    private function sendR18ChangeNotificationIfNeeded(Thread $thread, ?Response $response = null): void
    {
        $hasAdultContentRestriction = in_array('成人向けコンテンツが含まれる', $thread->getRestrictionReasons(), true);

        if (!$hasAdultContentRestriction && $response) {
            $hasAdultContentRestriction = in_array('成人向けコンテンツが含まれる', $response->getRestrictionReasons(), true);
        }

        if (!$hasAdultContentRestriction) {
            return;
        }

        $threadCreator = $thread->user;
        if (!$threadCreator || !$threadCreator->isAdult()) {
            return;
        }

        $alreadySent = \App\Models\AdminMessage::where('thread_id', $thread->thread_id)
            ->where('title_key', 'r18_change_request_title')
            ->exists();

        if ($alreadySent) {
            return;
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $body = \App\Services\LanguageService::trans('r18_change_request_body', $lang, [
            'thread_title' => $thread->title,
        ]);
        $body = str_replace('\\n', "\n", $body);

        \App\Models\AdminMessage::create([
            'title_key' => 'r18_change_request_title',
            'body' => $body,
            'audience' => 'members',
            'user_id' => $threadCreator->user_id,
            'thread_id' => $thread->thread_id,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
            'unlimited_reply' => false,
            'is_auto_sent' => true,
        ]);
    }
}

