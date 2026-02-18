<?php

namespace App\Http\Controllers;

use App\Models\Report;
use App\Models\Suggestion;
use App\Models\Thread;
use App\Models\Response;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use App\Models\AdminMessage;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;
use App\Policies\AdminPolicy;
use Illuminate\Support\Facades\Auth;

class AdminController extends Controller
{
    /**
     * 通報の集計一覧（同一スレッド/レスポンスをまとめ、通報数の多い順）
     */
    public function reports()
    {
        // IDOR防止: 管理者機能へのアクセス権限をチェック
        $user = Auth::user();
        if ($user) {
            $policy = new AdminPolicy();
            if (!$policy->manageReports($user)) {
                abort(403, 'この操作を実行する権限がありません');
            }
        }
        
        $showApproved = request()->boolean('show_approved', false);
        $showRejected = request()->boolean('show_rejected', false);
        $onlyFlagged = request()->boolean('only_flagged', false);
        $onlyReReported = request()->boolean('only_re_reported', false);

        // 直前の通報一覧訪問
        $prevReportsVisit = \App\Models\AccessLog::where('type', 'admin_reports_visit')
            ->orderByDesc('created_at')
            ->first();
        $reportsSince = $prevReportsVisit?->created_at ?? now()->subYears(10);

        $base = Report::query();
        // 表示ポリシー:
        // - 既定: 未処理のみ（is_approved IS NULL）
        // - 承認表示ON: 承認済みも含める
        // - 拒否表示ON: 拒否済みも含める
        // ステータス判定は approved_at の有無で行う（未処理=approved_at NULL）
        if (!$showApproved && !$showRejected) {
            $base->whereNull('approved_at');
        } else {
            if (!$showApproved) {
                // 承認済みを除外
                $base->where(function ($q) {
                    $q->whereNull('approved_at')->orWhere(function($qq){ $qq->whereNotNull('approved_at')->where('is_approved', false); });
                });
            }
            if (!$showRejected) {
                // 拒否済みを除外
                $base->where(function ($q) {
                    $q->whereNull('approved_at')->orWhere(function($qq){ $qq->whereNotNull('approved_at')->where('is_approved', true); });
                });
            }
        }
        if ($onlyFlagged) {
            $base->where('flagged', true);
        }

        // thread/response のどちらか一方でグルーピング
        $groups = $base
            ->select([
                'thread_id',
                'response_id',
                DB::raw('COUNT(*) as reports_count'),
                DB::raw('MIN(created_at) as first_reported_at'),
                DB::raw('MAX(created_at) as last_reported_at'),
                DB::raw('BOOL_OR(flagged) as any_flagged'),
                DB::raw('BOOL_OR(approved_at IS NOT NULL AND is_approved IS TRUE) as any_approved'),
                DB::raw('BOOL_OR(approved_at IS NOT NULL AND is_approved IS FALSE) as any_rejected'),
            ])
            ->groupBy('thread_id', 'response_id')
            ->orderByDesc('reports_count')
            ->get();
        
        // 各グループに対して、一度拒否された後再通報されたかどうかを判定（N+1問題を回避）
        $threadIds = $groups->pluck('thread_id')->filter()->unique()->toArray();
        $responseIds = $groups->pluck('response_id')->filter()->unique()->toArray();
        
        // 拒否された通報を一括取得
        $rejectedThreadReports = Report::whereIn('thread_id', $threadIds)
            ->whereNotNull('approved_at')
            ->where('is_approved', false)
            ->pluck('thread_id')
            ->unique()
            ->toArray();
        
        $rejectedResponseReports = Report::whereIn('response_id', $responseIds)
            ->whereNotNull('approved_at')
            ->where('is_approved', false)
            ->pluck('response_id')
            ->unique()
            ->toArray();
        
        foreach ($groups as $group) {
            $hasPreviousRejection = false;
            
            if ($group->thread_id && in_array($group->thread_id, $rejectedThreadReports)) {
                $hasPreviousRejection = true;
            } elseif ($group->response_id && in_array($group->response_id, $rejectedResponseReports)) {
                $hasPreviousRejection = true;
            }
            
            $group->has_previous_rejection = $hasPreviousRejection;
        }
        
        // 再通報のみを表示するフィルター
        if ($onlyReReported) {
            $groups = $groups->filter(function($group) {
                return isset($group->has_previous_rejection) && $group->has_previous_rejection;
            });
        }

        // 新着数
        $newReportsCount = Report::where('created_at', '>', $reportsSince)->count();

        // 今回の訪問を記録
        try {
            \App\Models\AccessLog::create([
                'type' => 'admin_reports_visit',
                'user_id' => null,
                'path' => request()->path(),
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {}

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return view('admin.reports', [
            'groups' => $groups,
            'showApproved' => $showApproved,
            'showRejected' => $showRejected,
            'onlyFlagged' => $onlyFlagged,
            'onlyReReported' => $onlyReReported,
            'reportsSince' => $reportsSince,
            'newReportsCount' => $newReportsCount,
            'lang' => $lang,
        ]);
    }

    /** お知らせ配信フォーム/一覧 */
    public function messages()
    {
        // IDOR防止: 管理者機能へのアクセス権限をチェック
        $user = Auth::user();
        if ($user) {
            $policy = new AdminPolicy();
            if (!$policy->manageMessages($user)) {
                abort(403, 'この操作を実行する権限がありません');
            }
        }
        
        $filter = request('filter', 'all');
        
        // 送信済み（published_atがNULLでない）のお知らせのみ表示（is_welcome テンプレートは除く）
        $with = ['parentMessage'];
        if (Schema::hasTable('admin_message_recipients')) {
            $with[] = 'recipients';
        }
        $query = AdminMessage::whereNotNull('published_at')
            ->with($with);
        if (Schema::hasColumn('admin_messages', 'is_welcome')) {
            $query->where(function ($q) {
                $q->where('is_welcome', false)->orWhereNull('is_welcome');
            });
        }
        
        // フィルター適用
        switch ($filter) {
            case 'report_auto_reply':
                $query->whereNotNull('parent_message_id')
                    ->whereHas('parentMessage', function($q) {
                        $q->whereNotNull('user_id');
                    });
                break;
            case 'manual_reply':
                $query->whereNotNull('parent_message_id')
                    ->whereHas('parentMessage', function($q) {
                        $q->whereNull('user_id');
                    });
                break;
            case 'report_auto':
                $query->whereNotNull('user_id')
                    ->whereNull('parent_message_id');
                break;
            case 'members':
                // 会員一斉（条件指定含む）
                $query->whereNull('user_id')
                    ->whereDoesntHave('recipients')
                    ->where('audience', 'members')
                    ->whereNull('parent_message_id');
                break;
            case 'specific':
                // 特定の個人または複数人
                $query->whereNull('parent_message_id');
                if (Schema::hasTable('admin_message_recipients')) {
                    $query->where(function ($q) {
                        $q->whereNotNull('user_id')->orWhereHas('recipients');
                    });
                } else {
                    $query->whereNotNull('user_id');
                }
                break;
            case 'guests':
                $query->whereNull('user_id')
                    ->where('audience', 'guests')
                    ->whereNull('parent_message_id');
                break;
            case 'all':
            default:
                break;
        }
        
        $messages = $query->orderByDesc('published_at')
            ->orderByDesc('created_at')
            ->limit(50)
            ->get();
            
        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        // 初回登録時お知らせテンプレート（1件のみ・カラム存在時のみ）
        $welcomeMessage = null;
        if (Schema::hasColumn('admin_messages', 'is_welcome')) {
            $welcomeMessage = AdminMessage::where('is_welcome', true)->whereNull('published_at')->first();
        }

        return view('admin.messages', compact('messages', 'filter', 'lang', 'welcomeMessage'));
    }

    /** 初回登録時お知らせテンプレートを設定 */
    public function messagesSetWelcome()
    {
        if (!Schema::hasColumn('admin_messages', 'is_welcome')) {
            return back()->withErrors(['error' => 'マイグレーションを実行してください。']);
        }
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        request()->validate([
            'welcome_title' => 'nullable|string|max:255',
            'welcome_body' => 'required|string|max:10000',
            'welcome_coin_amount' => 'nullable|integer|min:0',
        ]);

        // 既存の welcome を解除
        AdminMessage::where('is_welcome', true)->update(['is_welcome' => false]);

        AdminMessage::create([
            'title' => request('welcome_title'),
            'body' => request('welcome_body'),
            'audience' => 'members',
            'published_at' => null,
            'is_welcome' => true,
            'allows_reply' => false,
            'unlimited_reply' => false,
            'reply_used' => false,
            'coin_amount' => request('welcome_coin_amount') ? (int)request('welcome_coin_amount') : null,
        ]);

        return back()->with('success', \App\Services\LanguageService::trans('admin_messages_welcome_set', $lang));
    }

    /** お知らせ配信 */
    public function messagesStore()
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        request()->validate([
            'target_type' => 'required|in:all_members,filtered,specific',
            'body' => 'required|string',
            'title' => 'nullable|string',
            'title_key' => 'nullable|string',
            'body_key' => 'nullable|string',
            'allows_reply' => 'nullable|boolean',
            'unlimited_reply' => 'nullable|boolean',
            'coin_amount' => 'nullable|integer|min:0',
            'target_is_adult' => 'nullable|in:0,1',
            'target_nationalities' => 'nullable|string|max:500',
            'target_registered_after' => 'nullable|date',
            'target_registered_before' => 'nullable|date',
            'recipient_identifiers' => 'nullable|string|max:2000',
        ]);

        $targetType = request('target_type');
        $userId = null;
        $recipientUserIds = [];

        if ($targetType === 'specific') {
            $raw = preg_replace('/\s+/', ',', trim((string) request('recipient_identifiers')));
            if ($raw === '') {
                return back()->withErrors(['recipient_identifiers' => \App\Services\LanguageService::trans('admin_messages_specific_required', $lang)]);
            }
            $parts = array_unique(array_filter(explode(',', $raw)));
            foreach ($parts as $part) {
                $part = trim($part);
                if (is_numeric($part)) {
                    $u = User::find((int) $part);
                    if ($u) {
                        $recipientUserIds[] = $u->user_id;
                    }
                } else {
                    $u = User::where('username', $part)->orWhere('user_identifier', $part)->first();
                    if ($u) {
                        $recipientUserIds[] = $u->user_id;
                    }
                }
            }
            $recipientUserIds = array_values(array_unique($recipientUserIds));
            if (empty($recipientUserIds)) {
                return back()->withErrors(['recipient_identifiers' => \App\Services\LanguageService::trans('admin_messages_specific_no_users', $lang)]);
            }
            if (count($recipientUserIds) === 1) {
                $userId = $recipientUserIds[0];
                $recipientUserIds = [];
            }
        }

        $targetIsAdult = null;
        $targetNationalities = null;
        $targetRegisteredAfter = null;
        $targetRegisteredBefore = null;
        if ($targetType === 'filtered') {
            if (request()->has('target_is_adult') && request('target_is_adult') !== '') {
                $targetIsAdult = (bool) request('target_is_adult');
            }
            $natRaw = trim((string) request('target_nationalities'));
            if ($natRaw !== '') {
                $targetNationalities = array_values(array_unique(array_filter(array_map('trim', explode(',', $natRaw)))));
            }
            if (request()->filled('target_registered_after')) {
                $targetRegisteredAfter = request('target_registered_after');
            }
            if (request()->filled('target_registered_before')) {
                $targetRegisteredBefore = request('target_registered_before');
            }
        }

        $message = AdminMessage::create([
            'title_key' => request('title_key'),
            'body_key' => request('body_key'),
            'title' => request('title'),
            'body' => request('body'),
            'audience' => 'members',
            'published_at' => now(),
            'user_id' => $userId,
            'allows_reply' => request('allows_reply', false),
            'unlimited_reply' => request('unlimited_reply', false),
            'reply_used' => false,
            'coin_amount' => request('coin_amount') ? (int) request('coin_amount') : null,
            'target_is_adult' => $targetIsAdult,
            'target_nationalities' => $targetNationalities ?: null,
            'target_registered_after' => $targetRegisteredAfter,
            'target_registered_before' => $targetRegisteredBefore,
        ]);

        foreach ($recipientUserIds as $uid) {
            $message->recipients()->attach($uid);
        }

        return back()->with('success', \App\Services\LanguageService::trans('admin_messages_sent_success', $lang));
    }

    /** お知らせ送信取り消し */
    public function messagesCancel(int $messageId)
    {
        $message = AdminMessage::find($messageId);
        
        if (!$message) {
            return back()->withErrors(['error' => 'お知らせが見つかりません']);
        }

        // published_atをNULLにして送信を取り消し
        $message->published_at = null;
        $message->save();

        return back()->with('success', 'お知らせの送信を取り消しました');
    }

    /** ダッシュボード */
    public function dashboard()
    {
        // IDOR防止: 管理者機能へのアクセス権限をチェック
        $user = Auth::user();
        if ($user) {
            $policy = new AdminPolicy();
            if (!$policy->accessAdmin($user)) {
                abort(403, 'この操作を実行する権限がありません');
            }
        }
        
        $lastGuest = \App\Models\AccessLog::where('type', 'admin_visit')->orderByDesc('created_at')->first();
        $lastLogin = \App\Models\AccessLog::where('type', 'admin_login')->orderByDesc('created_at')->first();

        // 直前のダッシュボード訪問以降に来た件数をカウント
        $prevDashboardVisit = \App\Models\AccessLog::where('type', 'admin_dashboard_visit')
            ->orderByDesc('created_at')
            ->first();
        $since = $prevDashboardVisit?->created_at ?? now()->subYears(10);
        $newReports = Report::where('created_at', '>', $since)->count();
        $newSuggestions = \App\Models\Suggestion::where('created_at', '>', $since)->count();

        // 今回のダッシュボード訪問を記録（集計後に記録）
        try {
            \App\Models\AccessLog::create([
                'type' => 'admin_dashboard_visit',
                'user_id' => null,
                'path' => request()->path(),
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {}

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return view('admin.index', [
            'lastGuest' => $lastGuest,
            'lastLogin' => $lastLogin,
            'newReports' => $newReports,
            'newSuggestions' => $newSuggestions,
            'lang' => $lang,
        ]);
    }

    // 詳細ページ（スレッド単位）
    public function reportThreadDetail(int $threadId)
    {
        $showApproved = request()->boolean('show_approved', false);
        $onlyFlagged = request()->boolean('only_flagged', false);

        $reports = Report::where('thread_id', $threadId);
        if (!$showApproved) {
            $reports->where(function ($q) {
                $q->whereNull('approved_at')->orWhere(function($qq){ $qq->whereNotNull('approved_at')->where('is_approved', false); });
            });
        }
        if ($onlyFlagged) {
            $reports->where('flagged', true);
        }
        $reports = $reports->orderBy('created_at', 'asc')->get();
        $thread = Thread::withTrashed()->with(['responses' => function($q){ $q->orderBy('created_at','asc'); }])->find($threadId);
        $groupFlagged = $reports->contains('flagged', true);

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return view('admin.report_detail_thread', [
            'threadId' => $threadId,
            'reports' => $reports,
            'thread' => $thread,
            'groupFlagged' => $groupFlagged,
            'showApproved' => $showApproved,
            'onlyFlagged' => $onlyFlagged,
            'lang' => $lang,
        ]);
    }

    // 詳細ページ（レスポンス単位）
    public function reportResponseDetail(int $responseId)
    {
        $showApproved = request()->boolean('show_approved', false);
        $onlyFlagged = request()->boolean('only_flagged', false);

        $reports = Report::where('response_id', $responseId);
        if (!$showApproved) {
            $reports->where(function ($q) {
                $q->whereNull('approved_at')->orWhere(function($qq){ $qq->whereNotNull('approved_at')->where('is_approved', false); });
            });
        }
        if ($onlyFlagged) {
            $reports->where('flagged', true);
        }
        $reports = $reports->orderBy('created_at', 'asc')->get();
        $response = Response::with(['thread.responses' => function($q){ $q->orderBy('created_at','asc'); }])->find($responseId);
        $groupFlagged = $reports->contains('flagged', true);

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return view('admin.report_detail_response', [
            'responseId' => $responseId,
            'reports' => $reports,
            'response' => $response,
            'groupFlagged' => $groupFlagged,
            'showApproved' => $showApproved,
            'onlyFlagged' => $onlyFlagged,
            'lang' => $lang,
        ]);
    }

    // グループ承認（スレッド単位）
    public function approveThreadReports(int $threadId)
    {
        $thread = Thread::withTrashed()->find($threadId);
        if (!$thread) {
            return back()->withErrors(['error' => 'スレッドが見つかりません']);
        }
        
        // 通報を承認（通報順位を取得するため、created_atでソート）
        $reports = Report::where('thread_id', $threadId)
            ->whereNull('approved_at')
            ->orderBy('created_at', 'asc')
            ->get();
        
        // 通報理由を収集（重複を除く）
        $reasons = $reports->pluck('reason')->unique()->toArray();
        $reasonsText = implode('、', $reasons);
        
        // 各通報にアウト数を設定（デフォルト値を使用）
        $updates = [];
        foreach ($reports as $report) {
            if (!$report->out_count) {
                $defaultOutCount = Report::getDefaultOutCount($report->reason);
                $updates[$report->report_id] = $defaultOutCount;
            }
        }
        
        // アウト数を一括更新
        foreach ($updates as $reportId => $outCount) {
            Report::where('report_id', $reportId)->update(['out_count' => $outCount]);
        }
        
        Report::where('thread_id', $threadId)
            ->whereNull('approved_at')
            ->update([
                'is_approved' => true,
                'approved_at' => now(),
            ]);
        
        // アウト数を再取得（更新後の値）
        $reports = Report::where('thread_id', $threadId)
            ->where('is_approved', true)
            ->whereNotNull('approved_at')
            ->get();
        
        // アウト数が設定されていない通報にデフォルト値を設定
        foreach ($reports as $report) {
            if (!$report->out_count) {
                $defaultOutCount = Report::getDefaultOutCount($report->reason);
                $report->out_count = $defaultOutCount;
                $report->save();
            }
        }
        
        // スレッド作成者のアウト数と凍結処理
        if ($threadOwner) {
            $this->processUserOutCountAndFreeze($threadOwner);
        }
        
        // 各通報者にメッセージを送信（通報順位を渡す）
        $rank = 1;
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendApprovalMessage($report->user_id, 'thread', $thread->title, null, $rank);
                $rank++;
            }
        }
        
        // スレッド主にお知らせを送信
        $threadOwner = $thread->user;
        if ($threadOwner) {
            $this->sendThreadDeletionNotice($threadOwner->user_id, $thread->title, $reasonsText);
        }
        
        // スレッドをソフトデリート
        $thread->delete();
        
        return back();
    }

    // グループ拒否（スレッド単位）
    public function rejectThreadReports(int $threadId)
    {
        $thread = Thread::withTrashed()->find($threadId);
        if (!$thread) {
            return back()->withErrors(['error' => 'スレッドが見つかりません']);
        }
        
        // 通報を拒否
        $reports = Report::where('thread_id', $threadId)
            ->whereNull('approved_at')
            ->get();
        
        Report::where('thread_id', $threadId)
            ->whereNull('approved_at')
            ->update([
                'is_approved' => false,
                'approved_at' => now(),
            ]);
        
        // 各通報者にメッセージを送信（返信可能）
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendRejectionMessage($report->user_id, 'thread', $thread->title, null);
            }
        }
        
        return back();
    }

    // グループフラグ切替（スレッド単位）
    public function toggleThreadFlag(int $threadId)
    {
        $flag = !Report::where('thread_id', $threadId)->where('flagged', true)->exists();
        Report::where('thread_id', $threadId)->update(['flagged' => $flag]);
        return back();
    }

    // グループ承認（レスポンス単位）
    public function approveResponseReports(int $responseId)
    {
        $response = Response::find($responseId);
        if (!$response) {
            return back()->withErrors(['error' => 'レスポンスが見つかりません']);
        }
        
        $thread = $response->thread;
        
        // 通報を承認（通報順位を取得するため、created_atでソート）
        $reports = Report::where('response_id', $responseId)
            ->whereNull('approved_at')
            ->orderBy('created_at', 'asc')
            ->get();
        
        // 各通報にアウト数を設定（デフォルト値を使用）
        $updates = [];
        foreach ($reports as $report) {
            if (!$report->out_count) {
                $defaultOutCount = Report::getDefaultOutCount($report->reason);
                $updates[$report->report_id] = $defaultOutCount;
            }
        }
        
        // アウト数を一括更新
        foreach ($updates as $reportId => $outCount) {
            Report::where('report_id', $reportId)->update(['out_count' => $outCount]);
        }
        
        Report::where('response_id', $responseId)
            ->whereNull('approved_at')
            ->update([
                'is_approved' => true,
                'approved_at' => now(),
            ]);
        
        // アウト数を再取得（更新後の値）
        $reports = Report::where('response_id', $responseId)
            ->where('is_approved', true)
            ->whereNotNull('approved_at')
            ->get();
        
        // アウト数が設定されていない通報にデフォルト値を設定
        foreach ($reports as $report) {
            if (!$report->out_count) {
                $defaultOutCount = Report::getDefaultOutCount($report->reason);
                $report->out_count = $defaultOutCount;
                $report->save();
            }
        }
        
        // レスポンス作成者のアウト数と凍結処理
        $responseOwner = $response->user;
        if ($responseOwner) {
            $this->processUserOutCountAndFreeze($responseOwner);
        }
        
        // 各通報者にメッセージを送信（通報順位を渡す）
        $rank = 1;
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendApprovalMessage($report->user_id, 'response', $thread->title ?? '（タイトルなし）', $response->body, $rank);
                $rank++;
            }
        }
        
        return back();
    }

    // グループ拒否（レスポンス単位）
    public function rejectResponseReports(int $responseId)
    {
        $response = Response::find($responseId);
        if (!$response) {
            return back()->withErrors(['error' => 'レスポンスが見つかりません']);
        }
        
        $thread = $response->thread;
        
        // 通報を拒否
        $reports = Report::where('response_id', $responseId)
            ->whereNull('approved_at')
            ->get();
        
        Report::where('response_id', $responseId)
            ->whereNull('approved_at')
            ->update([
                'is_approved' => false,
                'approved_at' => now(),
            ]);
        
        // 各通報者にメッセージを送信（返信可能）
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendRejectionMessage($report->user_id, 'response', $thread->title ?? '（タイトルなし）', $response->body);
            }
        }
        
        return back();
    }

    // グループフラグ切替（レスポンス単位）
    public function toggleResponseFlag(int $responseId)
    {
        $flag = !Report::where('response_id', $responseId)->where('flagged', true)->exists();
        Report::where('response_id', $responseId)->update(['flagged' => $flag]);
        return back();
    }

    /**
     * 了承時の自動メッセージを送信
     * 
     * @param int $userId 通報者のユーザーID
     * @param string $type 'thread' または 'response'
     * @param string $threadTitle スレッドタイトル
     * @param string|null $responseBody レスポンス本文（レスポンスの場合）
     * @param int $rank 通報順位（1から開始）
     */
    private function sendApprovalMessage(int $userId, string $type, string $threadTitle, ?string $responseBody, int $rank = 1)
    {
        $contentType = $type === 'thread' ? 'スレッド' : ($type === 'response' ? 'レスポンス' : 'プロフィール');
        $content = $type === 'thread' 
            ? $threadTitle 
            : ($type === 'response' ? $threadTitle . "\n\n" . $responseBody : $threadTitle);
        
        $bodyJa = "下記の{$contentType}において、通報いただいた内容を確認の上、違反投稿として対応いたしました。\n\n{$content}\n\nご協力ありがとうございました。";
        $bodyEn = "We have reviewed the reported content regarding the following {$contentType} and have taken action as a violation.\n\n{$content}\n\nThank you for your cooperation.";
        
        // 通報者のスコアを取得
        $userScore = Report::calculateUserReportScore($userId);
        
        // コイン数を決定
        $coinAmount = $this->calculateCoinAmount($rank, $userScore);
        
        AdminMessage::create([
            'title' => '通報内容対応完了のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members', // 個人向けだが、audienceはmembersとして設定
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
            'coin_amount' => $coinAmount,
        ]);
    }

    /**
     * スレッド削除通知をスレッド主に送信
     */
    private function sendThreadDeletionNotice(int $userId, string $threadTitle, string $reasons)
    {
        $bodyJa = "お客様が作成されたスレッド「{$threadTitle}」について、複数のユーザーから通報があり、運営で内容を確認した結果、以下の理由により削除いたしました。\n\n【通報理由】\n{$reasons}\n\n今後は利用規約を遵守した投稿をお願いいたします。";
        $bodyEn = "Your thread \"{$threadTitle}\" has been deleted following multiple reports and review by our moderation team for the following reasons:\n\n【Reasons】\n{$reasons}\n\nPlease ensure your future posts comply with our terms of service.";
        
        AdminMessage::create([
            'title' => 'スレッド削除のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members',
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
    }

    /**
     * 通報順位とスコアに基づいてコイン数を計算
     * 
     * @param int $rank 通報順位（1から開始）
     * @param float $userScore 通報者のスコア（0.3〜0.8）
     * @return int コイン数（0〜5）
     */
    private function calculateCoinAmount(int $rank, float $userScore): int
    {
        // 6位以降はコイン配布なし
        if ($rank > 5) {
            return 0;
        }
        
        // 1位〜3位
        if ($rank <= 3) {
            if ($userScore >= 0.7 && $userScore <= 0.8) {
                return 5;
            } elseif ($userScore >= 0.5 && $userScore < 0.7) {
                return 4;
            } elseif ($userScore >= 0.3 && $userScore < 0.5) {
                return 3;
            }
        }
        
        // 4位〜5位
        if ($rank >= 4 && $rank <= 5) {
            if ($userScore >= 0.7 && $userScore <= 0.8) {
                return 3;
            } elseif ($userScore >= 0.5 && $userScore < 0.7) {
                return 2;
            } elseif ($userScore >= 0.3 && $userScore < 0.5) {
                return 1;
            }
        }
        
        // デフォルト（スコアが範囲外の場合）
        return 0;
    }

    /**
     * 拒否時の自動メッセージを送信（返信可能）
     */
    private function sendRejectionMessage(int $userId, string $type, string $threadTitle, ?string $responseBody)
    {
        $contentType = $type === 'thread' ? 'スレッド' : ($type === 'response' ? 'レスポンス' : 'プロフィール');
        $content = $type === 'thread' 
            ? $threadTitle 
            : ($type === 'response' ? $threadTitle . "\n\n" . $responseBody : $threadTitle);
        
        $bodyJa = "下記の{$contentType}において、通報いただいた内容を確認しましたが、現時点では違反投稿には該当しませんでした。\n\n{$content}\n\n通報内容に補足がある場合は、返信にて追記をお願いします。";
        $bodyEn = "We have reviewed the reported content regarding the following {$contentType}, but at this time it does not constitute a violation.\n\n{$content}\n\nIf you have any additional information about the report, please add it in your reply.";
        
        AdminMessage::create([
            'title' => '通報内容対応完了のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members', // 個人向けだが、audienceはmembersとして設定
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => true,
            'reply_used' => false,
        ]);
    }

    /**
     * 改善要望採用時の自動メッセージを送信
     */
    private function sendSuggestionApprovalMessage(int $userId, string $suggestionMessage, int $coinAmount)
    {
        $bodyJa = "ご提出いただいた改善要望を確認の上、採用させていただきました。\n\nご要望内容：\n{$suggestionMessage}\n\nご協力ありがとうございました。";
        $bodyEn = "We have reviewed your suggestion and have decided to adopt it.\n\nYour suggestion:\n{$suggestionMessage}\n\nThank you for your cooperation.";
        
        AdminMessage::create([
            'title' => '改善要望採用のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members',
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
            'coin_amount' => $coinAmount,
        ]);
    }

    /**
     * 改善要望非採用時の自動メッセージを送信（返信可能）
     */
    private function sendSuggestionRejectionMessage(int $userId, string $suggestionMessage)
    {
        $bodyJa = "ご提出いただいた改善要望を確認しましたが、現時点では採用を見送らせていただきました。\n\nご要望内容：\n{$suggestionMessage}\n\nご要望内容に補足がある場合は、返信にて追記をお願いします。";
        $bodyEn = "We have reviewed your suggestion, but at this time we have decided not to adopt it.\n\nYour suggestion:\n{$suggestionMessage}\n\nIf you have any additional information about your suggestion, please add it in your reply.";
        
        AdminMessage::create([
            'title' => '改善要望対応完了のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members',
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => true,
            'reply_used' => false,
        ]);
    }

    /**
     * 改善要望一覧（古い順） + フィルタ（完了表示、星のみ）
     */
    public function suggestions()
    {
        $query = Suggestion::query();

        // 直前の改善要望一覧訪問
        $prevSuggestionsVisit = \App\Models\AccessLog::where('type', 'admin_suggestions_visit')
            ->orderByDesc('created_at')
            ->first();
        $suggestionsSince = $prevSuggestionsVisit?->created_at ?? now()->subYears(10);

        // デフォルト: 処理済み（採用/非採用）は非表示
        $showCompleted = request()->boolean('show_completed', false);
        if (!$showCompleted) {
            $query->whereNull('completed');
        }

        // 星付きのみ
        $onlyStarred = request()->boolean('only_starred', false);
        if ($onlyStarred) {
            $query->where('starred', true);
        }

        $suggestions = $query->orderBy('created_at', 'asc')->get();

        $newSuggestionsCount = \App\Models\Suggestion::where('created_at', '>', $suggestionsSince)->count();

        // 今回の訪問を記録
        try {
            \App\Models\AccessLog::create([
                'type' => 'admin_suggestions_visit',
                'user_id' => null,
                'path' => request()->path(),
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {}

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return view('admin.suggestions', [
            'suggestions' => $suggestions,
            'showCompleted' => $showCompleted,
            'onlyStarred' => $onlyStarred,
            'suggestionsSince' => $suggestionsSince,
            'newSuggestionsCount' => $newSuggestionsCount,
            'lang' => $lang,
        ]);
    }

    /** 改善要望を採用 */
    public function approveSuggestion(Request $request, Suggestion $suggestion)
    {
        // コイン数のバリデーション（3-8の範囲）
        $request->validate([
            'coin_amount' => 'required|integer|min:3|max:8',
        ]);
        
        $coinAmount = (int) $request->input('coin_amount');
        
        $suggestion->completed = true;
        $suggestion->coin_amount = $coinAmount;
        $suggestion->save();
        
        // ユーザーが存在する場合、お知らせを送信
        if ($suggestion->user_id) {
            $this->sendSuggestionApprovalMessage($suggestion->user_id, $suggestion->message, $coinAmount);
        }
        
        return back();
    }

    /** 改善要望を非採用 */
    public function rejectSuggestion(Suggestion $suggestion)
    {
        $suggestion->completed = false;
        $suggestion->save();
        
        // ユーザーが存在する場合、お知らせを送信
        if ($suggestion->user_id) {
            $this->sendSuggestionRejectionMessage($suggestion->user_id, $suggestion->message);
        }
        
        return back();
    }

    /** 星トグル */
    public function toggleSuggestionStar(Suggestion $suggestion)
    {
        $suggestion->starred = !$suggestion->starred;
        $suggestion->save();
        return back();
    }

    /**
     * ログファイルを表示
     */
    public function logs()
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        // ログファイルのパスを取得（ログローテーション対応）
        $logPath = $this->getLogFilePath();
        $lines = [];
        $totalLines = 0;
        $fileSize = 0;
        $fileExists = false;
        $searchKeyword = request()->get('search', '');
        
        if ($logPath && File::exists($logPath)) {
            $fileExists = true;
            // ファイルサイズを再取得（ログが更新されている可能性があるため）
            clearstatcache(true, $logPath);
            $fileSize = File::size($logPath);
            
            // ファイルサイズが大きい場合（10MB以上）は警告を表示
            $maxSize = 10 * 1024 * 1024; // 10MB
            
            // リクエストパラメータから取得する行数（デフォルト: 最後の1000行）
            $requestedLines = request()->integer('lines', 1000);
            $requestedLines = min($requestedLines, 10000); // 最大10000行まで
            
            // 最新のログから指定された行数分を取得する
            // ファイルの最後から読み込んで、最新のログから指定された行数分を取得
            $handle = fopen($logPath, 'r');
            if ($handle) {
                // ファイルの最後から読み始める
                // 必要な行数を確保するため、十分なサイズを読み込む（最大50MBに拡張）
                // ログファイルが更新されている可能性があるため、ファイルサイズを再取得
                $chunkSize = min(50 * 1024 * 1024, $fileSize);
                $position = max(0, $fileSize - $chunkSize);
                fseek($handle, $position);
                
                // 最初の行は不完全な可能性があるのでスキップ
                fgets($handle);
                
                // 残りの行を読み込む（最新のログが最後にあるため、そのまま追加）
                // 空行も含めてすべての行を読み込む（ログの構造を保持）
                $tempLines = [];
                while (($line = fgets($handle)) !== false) {
                    // 改行を削除（表示時に改行は保持される）
                    $line = rtrim($line, "\r\n");
                    // 空行も含める（ログの構造を保持するため）
                    $tempLines[] = $line;
                }
                fclose($handle);
                
                // 最後のN行のみを取得（最新のログが最後にあるため）
                // これにより、確実に最新のログから指定された行数分を取得できる
                $lines = array_slice($tempLines, -$requestedLines);
                
                // 新しい順に表示するため、逆順にする（最新のログが配列の最後にあるため）
                $lines = array_reverse($lines);
            }
            
            // 検索キーワードでフィルタリング
            if (!empty($searchKeyword)) {
                $filteredLines = [];
                foreach ($lines as $line) {
                    if (stripos($line, $searchKeyword) !== false) {
                        $filteredLines[] = $line;
                    }
                }
                $lines = $filteredLines;
            }
        }
        
        return view('admin.logs', [
            'lines' => $lines,
            'totalLines' => $totalLines,
            'fileSize' => $fileSize,
            'fileExists' => $fileExists,
            'logPath' => $logPath,
            'lang' => $lang,
            'searchKeyword' => $searchKeyword,
        ]);
    }

    /**
     * ログファイルのパスを取得（ログローテーション対応）
     */
    private function getLogFilePath(): ?string
    {
        $logsDir = storage_path('logs');
        
        // すべてのログファイルを取得（laravel.logとlaravel-*.log）
        $logFiles = [];
        
        // laravel.log を追加
        $defaultLogPath = $logsDir . '/laravel.log';
        if (File::exists($defaultLogPath)) {
            $logFiles[] = $defaultLogPath;
        }
        
        // 日付付きログファイルを追加
        $datedLogFiles = glob($logsDir . '/laravel-*.log');
        if ($datedLogFiles) {
            $logFiles = array_merge($logFiles, $datedLogFiles);
        }
        
        if (empty($logFiles)) {
            return null;
        }
        
        // ファイルの更新時刻でソート（最新が最後）
        usort($logFiles, function($a, $b) {
            $timeA = File::exists($a) ? File::lastModified($a) : 0;
            $timeB = File::exists($b) ? File::lastModified($b) : 0;
            return $timeA - $timeB;
        });
        
        // 最新のログファイルを返す
        return end($logFiles);
    }

    /**
     * ログファイルをダウンロード
     */
    public function downloadLogs()
    {
        $logPath = $this->getLogFilePath();
        
        if (!$logPath || !File::exists($logPath)) {
            abort(404, 'ログファイルが見つかりません');
        }
        
        $fileName = basename($logPath);
        
        return response()->download($logPath, $fileName, [
            'Content-Type' => 'text/plain',
        ]);
    }

    /**
     * ユーザーのアウト数と凍結処理を実行
     * 
     * @param User $user
     * @return void
     */
    private function processUserOutCountAndFreeze($user)
    {
        // 1年経過した通報のアウト数をリセット
        Report::resetExpiredOutCounts();
        
        // 現在のアウト数を計算
        $outCount = $user->calculateOutCount();
        $previousOutCount = $user->getAttribute('previous_out_count') ?? 0;
        
        // 4アウト以上の場合、永久凍結
        if ($user->shouldBePermanentlyBanned()) {
            $wasBanned = $user->is_permanently_banned;
            $user->is_permanently_banned = true;
            $user->frozen_until = null;
            $user->save();
            
            // 永久凍結ログを記録（初回のみ）
            if (!$wasBanned) {
                $user->logPermanentBan('アウト数が4以上に達したため永久凍結');
                $this->sendPermanentBanNotice($user);
            }
            return;
        }
        
        // 2アウト以上の場合、一時凍結
        if ($user->shouldBeTemporarilyFrozen()) {
            $wasFrozen = $user->frozen_until && $user->frozen_until->isFuture();
            $freezeDuration = $user->calculateFreezeDuration();
            if ($freezeDuration) {
                $oldFrozenUntil = $user->frozen_until;
                $user->frozen_until = $freezeDuration;
                $user->freeze_count++;
                $user->save();
                
                // 凍結ログを記録（新規凍結の場合のみ）
                if (!$wasFrozen) {
                    $user->logFreeze($freezeDuration, 'アウト数が2以上に達したため一時凍結');
                    $this->sendFreezeNotice($user, $freezeDuration);
                }
            }
        } else {
            // 1アウトの場合は警告のみ（凍結なし）
            if ($outCount >= 1.0 && $outCount < 2.0) {
                // 1アウト警告のお知らせ送信（最近送信されていない場合のみ）
                $recentWarning = AdminMessage::where('user_id', $user->user_id)
                    ->where('title', 'アウト警告のお知らせ')
                    ->where('created_at', '>=', now()->subWeek())
                    ->exists();
                
                if (!$recentWarning) {
                    $this->sendWarningNotice($user);
                }
            }
            
            // アウト数が0になった場合は凍結回数もリセット
            if ($outCount < 1.0 && $user->frozen_until) {
                $user->freeze_count = 0;
                $user->frozen_until = null;
                $user->save();
                // 凍結解除ログを記録
                $user->logFreeze(null, 'アウト数が0になったため凍結解除');
            }
        }
    }
    
    /**
     * 1アウト警告のお知らせを送信
     * 
     * @param User $user
     * @return void
     */
    private function sendWarningNotice($user)
    {
        $bodyJa = "お客様の投稿について、通報が承認されました。現在、アウト数が1に達しています。\n\n今後、同様の行為を続けると、アカウントが一時凍結または永久凍結される可能性があります。利用規約を遵守した投稿をお願いいたします。";
        $bodyEn = "A report regarding your post has been approved. Your out count has reached 1.\n\nIf you continue similar behavior, your account may be temporarily or permanently frozen. Please ensure your posts comply with our terms of service.";
        
        AdminMessage::create([
            'title' => 'アウト警告のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members',
            'user_id' => $user->user_id,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
    }
    
    /**
     * 凍結時のお知らせを送信
     * 
     * @param User $user
     * @param \Carbon\Carbon $freezeUntil
     * @return void
     */
    private function sendFreezeNotice($user, $freezeUntil)
    {
        $freezeUntilFormatted = $freezeUntil->format('Y年m月d日 H:i');
        $bodyJa = "お客様のアカウントが一時凍結されました。\n\n凍結解除予定日時: {$freezeUntilFormatted}\n\n凍結期間中は、閲覧以外の操作（スレッド・レスポンスの投稿、プロフィール編集、コイン獲得送信など）ができません。\n\n今後は利用規約を遵守した投稿をお願いいたします。";
        $bodyEn = "Your account has been temporarily frozen.\n\nFreeze release scheduled: {$freezeUntil->format('Y-m-d H:i')}\n\nDuring the freeze period, you cannot perform any operations except viewing (posting threads/responses, editing profile, sending coins, etc.).\n\nPlease ensure your future posts comply with our terms of service.";
        
        AdminMessage::create([
            'title' => 'アカウント一時凍結のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members',
            'user_id' => $user->user_id,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
    }
    
    /**
     * 永久凍結時のお知らせを送信
     * 
     * @param User $user
     * @return void
     */
    private function sendPermanentBanNotice($user)
    {
        $bodyJa = "お客様のアカウントが永久凍結されました。\n\n今後、このアカウントでログインすることはできますが、ログアウト以外の操作は一切できません。また、同じメールアドレスおよび電話番号での新規登録もできません。";
        $bodyEn = "Your account has been permanently banned.\n\nYou can still log in to this account, but you cannot perform any operations except logging out. Also, you cannot register a new account with the same email address or phone number.";
        
        AdminMessage::create([
            'title' => 'アカウント永久凍結のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members',
            'user_id' => $user->user_id,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
    }

    /**
     * プロフィール通報の承認処理
     * 
     * @param int $reportedUserId
     * @return void
     */
    public function approveProfileReports(int $reportedUserId)
    {
        $reportedUser = User::find($reportedUserId);
        if (!$reportedUser) {
            return back()->withErrors(['error' => 'ユーザーが見つかりません']);
        }
        
        // 通報を承認（通報順位を取得するため、created_atでソート）
        $reports = Report::where('reported_user_id', $reportedUserId)
            ->whereNull('approved_at')
            ->orderBy('created_at', 'asc')
            ->get();
        
        // 各通報にアウト数を設定（デフォルト値を使用）
        $updates = [];
        foreach ($reports as $report) {
            if (!$report->out_count) {
                $defaultOutCount = Report::getDefaultOutCount($report->reason);
                $updates[$report->report_id] = $defaultOutCount;
            }
        }
        
        // アウト数を一括更新
        foreach ($updates as $reportId => $outCount) {
            Report::where('report_id', $reportId)->update(['out_count' => $outCount]);
        }
        
        Report::where('reported_user_id', $reportedUserId)
            ->whereNull('approved_at')
            ->update([
                'is_approved' => true,
                'approved_at' => now(),
            ]);
        
        // アウト数を再取得（更新後の値）
        $reports = Report::where('reported_user_id', $reportedUserId)
            ->where('is_approved', true)
            ->whereNotNull('approved_at')
            ->get();
        
        // アウト数が設定されていない通報にデフォルト値を設定
        foreach ($reports as $report) {
            if (!$report->out_count) {
                $defaultOutCount = Report::getDefaultOutCount($report->reason);
                $report->out_count = $defaultOutCount;
                $report->save();
            }
        }
        
        // プロフィール所有者のアウト数と凍結処理
        $this->processUserOutCountAndFreeze($reportedUser);
        
        // 各通報者にメッセージを送信（通報順位を渡す）
        $rank = 1;
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendApprovalMessage($report->user_id, 'profile', $reportedUser->username, null, $rank);
                $rank++;
            }
        }
        
        return back();
    }

    /**
     * プロフィール通報の拒否処理
     * 
     * @param int $reportedUserId
     * @return void
     */
    public function rejectProfileReports(int $reportedUserId)
    {
        $reportedUser = User::find($reportedUserId);
        if (!$reportedUser) {
            return back()->withErrors(['error' => 'ユーザーが見つかりません']);
        }
        
        // 通報を拒否
        $reports = Report::where('reported_user_id', $reportedUserId)
            ->whereNull('approved_at')
            ->get();
        
        Report::where('reported_user_id', $reportedUserId)
            ->whereNull('approved_at')
            ->update([
                'is_approved' => false,
                'approved_at' => now(),
            ]);
        
        // 各通報者にメッセージを送信（返信可能）
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendRejectionMessage($report->user_id, 'profile', $reportedUser->username, null);
            }
        }
        
        return back();
    }

    /**
     * 通報のアウト数を設定
     * 
     * @param Request $request
     * @param int $reportId
     * @return \Illuminate\Http\RedirectResponse
     */
    public function setReportOutCount(Request $request, int $reportId)
    {
        $report = Report::find($reportId);
        if (!$report) {
            return back()->withErrors(['error' => '通報が見つかりません']);
        }
        
        $validated = $request->validate([
            'out_count' => 'required|numeric|min:0.5|max:3.0',
        ]);
        
        $report->out_count = $validated['out_count'];
        $report->save();
        
        // 承認済みの場合、対象ユーザーのアウト数と凍結処理を再計算
        if ($report->is_approved && $report->approved_at) {
            $targetUser = null;
            
            if ($report->thread_id) {
                $thread = Thread::withTrashed()->find($report->thread_id);
                $targetUser = $thread?->user;
            } elseif ($report->response_id) {
                $response = Response::find($report->response_id);
                $targetUser = $response?->user;
            } elseif ($report->reported_user_id) {
                $targetUser = User::find($report->reported_user_id);
            }
            
            if ($targetUser) {
                $this->processUserOutCountAndFreeze($targetUser);
            }
        }
        
        return back()->with('success', 'アウト数を設定しました');
    }

    /**
     * ログファイルをクリア
     */
    public function clearLogs()
    {
        $logPath = $this->getLogFilePath();
        
        if ($logPath && File::exists($logPath)) {
            File::put($logPath, '');
        }
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return back()->with('success', \App\Services\LanguageService::trans('admin_logs_cleared', $lang));
    }
}


