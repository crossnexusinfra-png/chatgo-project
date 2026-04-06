<?php

namespace App\Http\Controllers;

use App\Models\FreezeAppeal;
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
use App\Services\UserOutCountFreezeService;
use App\Services\UserOutCountReductionService;

class AdminController extends Controller
{
    public function __construct(
        private UserOutCountFreezeService $userOutCountFreezeService,
        private UserOutCountReductionService $userOutCountReductionService
    ) {
    }

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
            'body' => 'required|string|max:2000',
            'title' => 'nullable|string|max:255',
            'title_key' => 'nullable|string|max:255',
            'body_key' => 'nullable|string|max:255',
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
            $parts = array_unique(array_filter(array_map('trim', explode(',', $raw))));
            foreach ($parts as $part) {
                if ($part === '') {
                    continue;
                }
                // 表示形式「ユーザー名@ユーザーID」の「ユーザーID」部分（user_identifier）で検索
                $u = User::where('user_identifier', $part)->first();
                if ($u) {
                    $recipientUserIds[] = $u->user_id;
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
        $newFreezeAppeals = FreezeAppeal::where('status', 'pending')
            ->where('created_at', '>', $since)
            ->count();

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
            'newFreezeAppeals' => $newFreezeAppeals,
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
            return back()->withErrors(['error' => 'ルームが見つかりません']);
        }
        
        // 通報を承認（通報順位を取得するため、created_atでソート）
        $reports = Report::where('thread_id', $threadId)
            ->whereNull('approved_at')
            ->orderBy('created_at', 'asc')
            ->get();
        
        // 通報理由を収集（重複を除く・【通報理由】は各行「・」付き）
        $reasonsLines = $reports->pluck('reason')->filter()->unique()->values()->all();
        $reasonsText = $this->formatReportReasonsBulletList($reasonsLines);

        // 同一対象で複数理由がある場合は合算せず、最大アウト値1件のみ加算する。
        $this->approveReportsWithHighestOutCountOnly($reports);

        // 凍結・警告より先に作成者を解決（以前は代入順のバグで processOutCountAndFreeze が常にスキップされていた）
        $threadOwner = $thread->user;

        // スレッド作成者のアウト数と凍結処理
        if ($threadOwner) {
            $this->userOutCountFreezeService->processOutCountAndFreeze($threadOwner);
        }

        // 各通報者にメッセージを送信（通報順位を渡す）
        $rank = 1;
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendApprovalMessage($report->user_id, 'thread', $thread->title, null, $rank);
                $rank++;
            }
        }

        // スレッド主へ削除通知（管理者承認）
        if ($threadOwner) {
            $contentBlock = 'ルーム名：' . "\n" . $thread->title;
            $this->sendReportDeletionNoticeToAuthor($threadOwner->user_id, 'ルーム', $contentBlock, $reasonsText);
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
            return back()->withErrors(['error' => 'ルームが見つかりません']);
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
            return back()->withErrors(['error' => 'リプライが見つかりません']);
        }
        
        $thread = $response->thread;
        
        // 通報を承認（通報順位を取得するため、created_atでソート）
        $reports = Report::where('response_id', $responseId)
            ->whereNull('approved_at')
            ->orderBy('created_at', 'asc')
            ->get();
        
        // 通報理由を収集（重複を除く・【通報理由】は各行「・」付き）
        $reasonsLines = $reports->pluck('reason')->filter()->unique()->values()->all();
        $reasonsText = $this->formatReportReasonsBulletList($reasonsLines);

        // 同一対象で複数理由がある場合は合算せず、最大アウト値1件のみ加算する。
        $this->approveReportsWithHighestOutCountOnly($reports);

        // レスポンス作成者のアウト数と凍結処理
        $responseOwner = $response->user;
        if ($responseOwner) {
            $this->userOutCountFreezeService->processOutCountAndFreeze($responseOwner);
        }

        // 各通報者にメッセージを送信（通報順位を渡す）
        $rank = 1;
        $replySnippet = mb_substr($response->plainBodyOrMediaKindForNotifications(), 0, 2000);
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendApprovalMessage($report->user_id, 'response', $thread->title ?? '（タイトルなし）', $replySnippet, $rank);
                $rank++;
            }
        }

        // リプライ投稿者へ削除通知（管理者承認）
        if ($responseOwner) {
            $threadTitle = $thread->title ?? '（タイトルなし）';
            $contentBlock = 'ルーム名：' . "\n" . $threadTitle . "\n" . 'リプライ内容：' . "\n" . $replySnippet;
            $this->sendReportDeletionNoticeToAuthor($responseOwner->user_id, 'リプライ', $contentBlock, $reasonsText);
        }

        return back();
    }

    // グループ拒否（レスポンス単位）
    public function rejectResponseReports(int $responseId)
    {
        $response = Response::find($responseId);
        if (!$response) {
            return back()->withErrors(['error' => 'リプライが見つかりません']);
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
        $replySnippet = mb_substr($response->plainBodyOrMediaKindForNotifications(), 0, 2000);
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendRejectionMessage($report->user_id, 'response', $thread->title ?? '（タイトルなし）', $replySnippet);
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
     * 【通報理由】用：重複を除き各行「・」＋改行連結
     *
     * @param  array<int, string|null>  $reasons
     */
    private function formatReportReasonsBulletList(array $reasons): string
    {
        $lines = array_values(array_unique(array_filter(array_map('strval', $reasons), fn ($r) => $r !== '')));
        if ($lines === []) {
            return '';
        }

        return implode("\n", array_map(fn (string $r) => '・' . $r, $lines));
    }

    /**
     * 通報者向けお知らせの【通報対象】ブロック（ルーム／リプライ／プロフィール）
     *
     * @param  string  $type  'thread' | 'response' | 'profile'
     */
    private function formatReporterTargetBlock(string $type, string $threadTitle, ?string $responseBody): string
    {
        if ($type === 'thread') {
            return 'ルーム名：' . "\n" . $threadTitle;
        }
        if ($type === 'response') {
            $snippet = mb_substr(strip_tags((string) ($responseBody ?? '')), 0, 2000);

            return 'ルーム名：' . "\n" . $threadTitle . "\n" . 'リプライ内容：' . "\n" . $snippet;
        }

        return 'ユーザー名：' . "\n" . $threadTitle;
    }

    /** @param  string  $type  'thread' | 'response' | 'profile' */
    private function reporterContentTypeLabel(string $type): string
    {
        return match ($type) {
            'thread' => 'ルーム',
            'response' => 'リプライ',
            'profile' => 'プロフィール',
            default => 'ルーム',
        };
    }

    /**
     * 了承時の自動メッセージを送信
     * 
     * @param int $userId 通報者のユーザーID
     * @param string $type 'thread' または 'response'
     * @param string $threadTitle ルーム名（タイトル）
     * @param string|null $responseBody リプライ本文（リプライ通報の場合）
     * @param int $rank 通報順位（1から開始）
     */
    private function sendApprovalMessage(int $userId, string $type, string $threadTitle, ?string $responseBody, int $rank = 1)
    {
        $contentType = $this->reporterContentTypeLabel($type);
        $content = $this->formatReporterTargetBlock($type, $threadTitle, $responseBody);

        $bodyJa = "下記の{$contentType}において、通報いただいた内容を確認の上、違反投稿として対応いたしました。\n\n{$content}\n\nご協力ありがとうございました。";

        // 通報者のスコアを取得
        $userScore = Report::calculateUserReportScore($userId);
        
        // コイン数を決定
        $coinAmount = $this->calculateCoinAmount($rank, $userScore);
        
        AdminMessage::create([
            'title' => '通報内容の対応について',
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
     * 管理者が通報を承認したとき、被通報者（投稿者）へ削除通知を送る（ルーム／リプライ）
     *
     * @param  string  $contentTypeLabel  タイトル・本文先頭の種別（例: ルーム、リプライ）
     * @param  string  $contentBlock      【通報対象】ブロック全文
     * @param  string  $reasons             【通報理由】（改行連結）
     */
    private function sendReportDeletionNoticeToAuthor(int $userId, string $contentTypeLabel, string $contentBlock, string $reasons): void
    {
        $title = $contentTypeLabel . '削除のお知らせ';
        $bodyJa = "下記の{$contentTypeLabel}について、複数のユーザーから通報があり、運営で内容を確認した結果、以下の理由により削除いたしました。\n\n【通報対象】\n{$contentBlock}\n\n【通報理由】\n{$reasons}\n\n今後は利用規約を遵守した投稿をお願いいたします。";

        AdminMessage::create([
            'title' => $title,
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
     * 同一対象の承認通報は、最大アウト値の1件のみ加算し、他は0として承認する。
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Report> $reports
     */
    private function approveReportsWithHighestOutCountOnly(\Illuminate\Support\Collection $reports, float $multiplier = 1.0): void
    {
        if ($reports->isEmpty()) {
            return;
        }

        $maxOutCount = 0.0;
        $maxReportId = null;
        foreach ($reports as $report) {
            $base = (float) ($report->out_count ?: Report::getDefaultOutCount((string) $report->reason));
            $candidate = round($base * $multiplier, 1);
            if ($candidate > $maxOutCount) {
                $maxOutCount = $candidate;
                $maxReportId = $report->report_id;
            }
        }

        $approvedAt = now();
        foreach ($reports as $report) {
            $report->is_approved = true;
            $report->approved_at = $approvedAt;
            $report->out_count = ($maxReportId !== null && $report->report_id === $maxReportId) ? $maxOutCount : 0.0;
            $report->save();
        }
    }

    /**
     * 拒否時の自動メッセージを送信（返信可能）
     */
    private function sendRejectionMessage(int $userId, string $type, string $threadTitle, ?string $responseBody)
    {
        $contentType = $this->reporterContentTypeLabel($type);
        $content = $this->formatReporterTargetBlock($type, $threadTitle, $responseBody);

        $bodyJa = "下記の{$contentType}において、通報いただいた内容を確認しましたが、現時点では違反投稿には該当しないと判断いたしました。\n\n{$content}\n\n通報内容に補足がある場合は、返信にて追記をお願いします。";

        AdminMessage::create([
            'title' => '通報内容の対応について',
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
        $bodyJa = "ご提出いただいた改善要望を確認の上、参考にさせていただきました。\n\nご要望内容：\n{$suggestionMessage}\n\nご協力ありがとうございました。";

        AdminMessage::create([
            'title' => '改善要望の対応について',
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
        $bodyJa = "ご提出いただいた改善要望を確認しましたが、現時点では対応を見送らせていただきました。\n\nご要望内容：\n{$suggestionMessage}\n\nご要望内容に補足がある場合は、返信にて追記をお願いします。";

        AdminMessage::create([
            'title' => '改善要望の対応について',
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
        
        // プロフィール通報は凍結等の重みを調整できるよう係数化
        $profileOutMultiplier = (float) config('report_restrictions.profile_out_multiplier', 1.3);
        
        // 通報を承認（通報順位を取得するため、created_atでソート）
        $reports = Report::where('reported_user_id', $reportedUserId)
            ->whereNull('approved_at')
            ->orderBy('created_at', 'asc')
            ->get();
        
        // 同一対象で複数理由がある場合は合算せず、最大アウト値1件のみ加算する。
        $this->approveReportsWithHighestOutCountOnly($reports, $profileOutMultiplier);
        
        // プロフィール所有者のアウト数と凍結処理
        $this->userOutCountFreezeService->processOutCountAndFreeze($reportedUser);
        
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
                $this->userOutCountFreezeService->processOutCountAndFreeze($targetUser);
            }
        }
        
        return back()->with('success', 'アウト数を設定しました');
    }

    /**
     * 凍結異議申し立て一覧
     */
    public function freezeAppeals()
    {
        $user = Auth::user();
        if ($user) {
            $policy = new AdminPolicy();
            if (!$policy->manageReports($user)) {
                abort(403, 'この操作を実行する権限がありません');
            }
        }

        $prevVisit = \App\Models\AccessLog::where('type', 'admin_freeze_appeals_visit')
            ->orderByDesc('created_at')
            ->first();
        $appealsSince = $prevVisit?->created_at ?? now()->subYears(10);

        $showCompleted = request()->boolean('show_completed', false);
        $query = FreezeAppeal::query()->with('user')->orderBy('created_at', 'asc');
        if (!$showCompleted) {
            $query->where('status', 'pending');
        }
        $appeals = $query->get();

        $newAppealsCount = FreezeAppeal::where('status', 'pending')
            ->where('created_at', '>', $appealsSince)
            ->count();

        try {
            \App\Models\AccessLog::create([
                'type' => 'admin_freeze_appeals_visit',
                'user_id' => null,
                'path' => request()->path(),
                'ip' => request()->ip(),
            ]);
        } catch (\Throwable $e) {
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return view('admin.freeze-appeals', [
            'appeals' => $appeals,
            'showCompleted' => $showCompleted,
            'appealsSince' => $appealsSince,
            'newAppealsCount' => $newAppealsCount,
            'lang' => $lang,
        ]);
    }

    public function approveFreezeAppeal(Request $request, FreezeAppeal $freezeAppeal)
    {
        $user = Auth::user();
        if ($user) {
            $policy = new AdminPolicy();
            if (!$policy->manageReports($user)) {
                abort(403, 'この操作を実行する権限がありません');
            }
        }

        if (!$freezeAppeal->isPending()) {
            return back()->withErrors(['error' => '既に処理済みです']);
        }

        $target = User::find($freezeAppeal->user_id);
        if (!$target) {
            return back()->withErrors(['error' => 'ユーザーが見つかりません']);
        }

        $maxOut = max(0.25, round($target->calculateOutCount(), 2));
        $request->validate([
            'out_count_reduced' => 'required|numeric|min:0.25|max:' . $maxOut,
        ]);

        $amount = round((float) $request->input('out_count_reduced'), 2);

        try {
            DB::transaction(function () use ($freezeAppeal, $amount) {
                $a = FreezeAppeal::where('freeze_appeal_id', $freezeAppeal->freeze_appeal_id)
                    ->lockForUpdate()
                    ->first();
                if (!$a || $a->status !== 'pending') {
                    throw new \RuntimeException('appeal_not_pending');
                }
                $target = User::where('user_id', $a->user_id)->lockForUpdate()->first();
                if (!$target) {
                    throw new \RuntimeException('user_missing');
                }
                $this->userOutCountReductionService->subtractFromUserReports($target, $amount);
                $this->userOutCountFreezeService->processOutCountAndFreeze($target->fresh(), true);
                $a->status = 'approved';
                $a->out_count_reduced = $amount;
                $a->processed_at = now();
                $a->save();
            });
        } catch (\RuntimeException $e) {
            if ($e->getMessage() === 'appeal_not_pending') {
                return back()->withErrors(['error' => '既に処理済みです']);
            }
            if ($e->getMessage() === 'user_missing') {
                return back()->withErrors(['error' => 'ユーザーが見つかりません']);
            }

            throw $e;
        }

        try {
            $this->sendFreezeAppealApprovalMessage(
                (int) $freezeAppeal->user_id,
                $freezeAppeal->message
            );
        } catch (\Throwable $e) {
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return back()->with('success', \App\Services\LanguageService::trans('admin_freeze_appeal_approved_ok', $lang));
    }

    public function rejectFreezeAppeal(FreezeAppeal $freezeAppeal)
    {
        $user = Auth::user();
        if ($user) {
            $policy = new AdminPolicy();
            if (!$policy->manageReports($user)) {
                abort(403, 'この操作を実行する権限がありません');
            }
        }

        if (!$freezeAppeal->isPending()) {
            return back()->withErrors(['error' => '既に処理済みです']);
        }

        $freezeAppeal->status = 'rejected';
        $freezeAppeal->processed_at = now();
        $freezeAppeal->save();

        try {
            $this->sendFreezeAppealRejectionMessage((int) $freezeAppeal->user_id, $freezeAppeal->message);
        } catch (\Throwable $e) {
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return back()->with('success', \App\Services\LanguageService::trans('admin_freeze_appeal_rejected_ok', $lang));
    }

    /**
     * 対象ユーザーへの被通報一覧（管理者用）
     */
    public function freezeAppealUserReportHistory(int $userId)
    {
        $user = Auth::user();
        if ($user) {
            $policy = new AdminPolicy();
            if (!$policy->manageReports($user)) {
                abort(403, 'この操作を実行する権限がありません');
            }
        }

        $target = User::findOrFail($userId);

        $reports = Report::query()
            ->with([
                'thread' => fn ($q) => $q->withTrashed(),
                'response' => fn ($q) => $q->with(['thread' => fn ($qq) => $qq->withTrashed()]),
            ])
            ->where(function ($q) use ($userId) {
                $q->whereHas('thread', fn ($qq) => $qq->where('user_id', $userId))
                    ->orWhereHas('response', fn ($qq) => $qq->where('user_id', $userId))
                    ->orWhere('reported_user_id', $userId);
            })
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();

        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return view('admin.freeze-appeal-report-history', [
            'target' => $target,
            'reports' => $reports,
            'lang' => $lang,
        ]);
    }

    private function sendFreezeAppealApprovalMessage(int $userId, string $appealMessage): void
    {
        $bodyJa = "ご提出いただいた凍結に関する異議申し立てを確認の上、承認しました。\n\n"
            . "申し立て内容：\n{$appealMessage}\n\n"
            . '今後も利用規約を遵守したご利用をお願いいたします。';

        AdminMessage::create([
            'title' => '異議申し立ての対応について',
            'body' => $bodyJa,
            'audience' => 'members',
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
    }

    private function sendFreezeAppealRejectionMessage(int $userId, string $appealMessage): void
    {
        $bodyJa = "ご提出いただいた凍結に関する異議申し立てを確認しましたが、現時点ではお受けできないと判断いたしました。\n\n"
            . "申し立て内容：\n{$appealMessage}";

        AdminMessage::create([
            'title' => '異議申し立ての対応について',
            'body' => $bodyJa,
            'audience' => 'members',
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
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


