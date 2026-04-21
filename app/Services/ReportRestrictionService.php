<?php

namespace App\Services;

use App\Models\AdminMessage;
use App\Models\Report;
use App\Models\ReportRestriction;
use App\Models\Thread;
use App\Models\Response;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Database\QueryException;

class ReportRestrictionService
{
    private function getUserLanguageCode(int $userId): string
    {
        $user = User::where('user_id', $userId)->first();
        return strtoupper((string) ($user?->language ?? 'JA')) === 'EN' ? 'EN' : 'JA';
    }

    private function translateThreadTitleForUser(?Thread $thread, int $userId): string
    {
        if (!$thread) {
            return '（タイトルなし）';
        }
        $target = $this->getUserLanguageCode($userId);
        $source = TranslationService::normalizeLang((string) ($thread->source_lang ?? ($thread->user->language ?? 'JA')));
        return TranslationService::getTranslatedThreadTitle((int) $thread->thread_id, (string) $thread->title, $target, $source, false);
    }

    private function responseSnippetByRule(Response $response, int $userId, bool $useOriginal): string
    {
        $body = trim((string) ($response->body ?? ''));
        if ($body === '') {
            $target = $this->getUserLanguageCode($userId);
            $type = (string) ($response->media_type ?? '');
            if ($target === 'EN') {
                return match ($type) {
                    'image' => 'Image',
                    'video' => 'Video',
                    'audio' => 'Audio',
                    default => !empty($response->media_file) ? 'Media' : '',
                };
            }
            return match ($type) {
                'image' => '画像',
                'video' => '動画',
                'audio' => '音声',
                default => !empty($response->media_file) ? 'メディア' : '',
            };
        }

        if ($useOriginal) {
            return $this->safeTrim(strip_tags($body), 2000, '…');
        }

        $target = $this->getUserLanguageCode($userId);
        $source = TranslationService::normalizeLang((string) ($response->source_lang ?? ($response->user->language ?? 'JA')));
        $translated = TranslationService::getTranslatedResponseBody((int) $response->response_id, $body, $target, null, $source, false);
        return $this->safeTrim(strip_tags($translated), 2000, '…');
    }

    public function __construct(
        private UserOutCountFreezeService $userOutCountFreezeService
    ) {
    }

    public function ensureRestrictionCreatedForThread(Thread $thread): ?ReportRestriction
    {
        if (!$thread->user_id) {
            return null;
        }
        if (!$thread->isRestricted()) {
            return null;
        }

        return $this->firstOrCreateActive([
            'type' => 'thread',
            'user_id' => (int) $thread->user_id,
            'thread_id' => (int) $thread->thread_id,
        ], function () use ($thread) {
            $reasons = $this->getReportReasonsForThread($thread->thread_id);
            $lang = $this->getUserLanguageCode((int) $thread->user_id);
            $body = $this->buildRestrictionNoticeBody('thread', $thread->title, null, $reasons, $lang);
            $message = $this->sendRestrictionAckMessage([
                'user_id' => (int) $thread->user_id,
                'thread_id' => (int) $thread->thread_id,
                'title_key' => 'report_restriction_review_title',
                'body_key' => null,
                'body' => $body,
            ]);
            $this->sendR18ChangeRequestIfNeeded($thread);
            return $message;
        });
    }

    public function ensureRestrictionCreatedForResponse(Response $response): ?ReportRestriction
    {
        if (!$response->user_id) {
            return null;
        }
        if (!$response->shouldBeHidden()) {
            return null;
        }

        return $this->firstOrCreateActive([
            'type' => 'response',
            'user_id' => (int) $response->user_id,
            'response_id' => (int) $response->response_id,
            'thread_id' => (int) $response->thread_id,
        ], function () use ($response) {
            // リプライ系通知はルーム名を設定言語で表示
            $threadTitle = $this->translateThreadTitleForUser($response->thread, (int) $response->user_id);
            $replySnippet = $this->responseSnippetByRule($response, (int) $response->user_id, true);
            $reasons = $this->getReportReasonsForResponse($response->response_id);
            $lang = $this->getUserLanguageCode((int) $response->user_id);
            $body = $this->buildRestrictionNoticeBody('response', $threadTitle, $replySnippet, $reasons, $lang);
            $message = $this->sendRestrictionAckMessage([
                'user_id' => (int) $response->user_id,
                'thread_id' => (int) $response->thread_id,
                'response_id' => (int) $response->response_id,
                'title_key' => 'report_restriction_review_title',
                'body_key' => null,
                'body' => $body,
            ]);
            if ($response->thread) {
                $this->sendR18ChangeRequestIfNeeded($response->thread);
            }
            return $message;
        });
    }

    public function isUserRestrictedNow(int $userId): bool
    {
        return ReportRestriction::where('user_id', $userId)->where('status', 'active')->exists();
    }

    public function activeRestrictionCount(int $userId): int
    {
        return (int) ReportRestriction::where('user_id', $userId)->where('status', 'active')->count();
    }

    /**
     * @return array{found_restriction:bool, used_fallback:bool, type:?string, thread_id:?int, response_id:?int, approved_reports:int, deleted_thread:bool, deleted_response:bool}
     */
    public function acknowledgeFromMessage(AdminMessage $message): array
    {
        return DB::transaction(function () use ($message) {
            try {
                /** @var User|null $user */
                $user = User::find($message->user_id);
                if (!$user) {
                    throw new \RuntimeException('[ACK_STEP]user_not_found');
                }

                // report_restrictions テーブルが本番で未反映でも了承処理は完走させる
                $restriction = null;
                $hasRestrictionsTable = false;
                try {
                    $hasRestrictionsTable = Schema::hasTable('report_restrictions');
                } catch (\Throwable $e) {
                    // 権限/接続差分などで information_schema 参照が落ちるケースもあるため握る
                    $hasRestrictionsTable = false;
                }

                if ($hasRestrictionsTable) {
                    try {
                        $restriction = ReportRestriction::where('admin_message_id', $message->id)
                            ->where('user_id', $user->user_id)
                            ->where('status', 'active')
                            ->first();
                    } catch (\Throwable $e) {
                        // テーブル/カラム差分等で参照に失敗してもフォールバックする
                        $restriction = null;
                    }
                }

                $result = [
                    'found_restriction' => (bool) $restriction,
                    'used_fallback' => false,
                    'type' => null,
                    'thread_id' => null,
                    'response_id' => null,
                    'approved_reports' => 0,
                    'deleted_thread' => false,
                    'deleted_response' => false,
                ];

                if (!$restriction) {
                    // 既に処理済み/存在しない or テーブル未反映の場合はメッセージの参照先で処理する
                    $fallbackType = $message->response_id ? 'response' : ($message->thread_id ? 'thread' : null);
                    if ($fallbackType === null) {
                        // 対象不明（本番で admin_messages の thread_id/response_id カラムが未反映等）
                        throw new \RuntimeException('[ACK_STEP]target_not_identified');
                    }
                    $type = $fallbackType;
                    $threadId = $message->thread_id ? (int) $message->thread_id : null;
                    $responseId = $message->response_id ? (int) $message->response_id : null;
                    $result['used_fallback'] = true;
                } else {
                    $type = $restriction->type;
                    $threadId = $restriction->thread_id ? (int) $restriction->thread_id : null;
                    $responseId = $restriction->response_id ? (int) $restriction->response_id : null;
                }

                // 承認と同様に処理（仕様変更: 自認によるアウト数軽減は行わない）
                $selfAckMultiplier = 1.0;

                $result['type'] = $type;
                $result['thread_id'] = $threadId;
                $result['response_id'] = $responseId;

                // 管理者側で既に承認/拒否済み（=未処理が残っていない）対象は、作成者了承を不可にする
                $processingState = $this->getReportProcessingState($type, $threadId, $responseId);
                if ($processingState['pending'] === 0 && $processingState['processed'] > 0) {
                    throw new \RuntimeException('[ACK_STEP]already_moderated');
                }

                // R18ルーム変更後でも「成人向けコンテンツが含まれる」通報理由に対する了承のみ不可
                $r18CheckThreadId = $threadId;
                if ($r18CheckThreadId === null && $responseId) {
                    try {
                        $r18ProbeResponse = Response::find($responseId);
                        if ($r18ProbeResponse && $r18ProbeResponse->thread_id) {
                            $r18CheckThreadId = (int) $r18ProbeResponse->thread_id;
                        }
                    } catch (\Throwable $e) {
                        $r18CheckThreadId = null;
                    }
                }
                if ($r18CheckThreadId !== null) {
                    try {
                        $r18ProbeThread = Thread::withTrashed()->find($r18CheckThreadId);
                    } catch (\Throwable $e) {
                        $r18ProbeThread = null;
                    }
                    if (
                        $r18ProbeThread
                        && $r18ProbeThread->is_r18
                        && $this->hasAdultContentReasonPending($type, $threadId, $responseId)
                    ) {
                        throw new \RuntimeException('[ACK_STEP]r18_ack_not_allowed');
                    }
                }

                if ($type === 'thread' && $threadId) {
                    try {
                        $thread = Thread::withTrashed()->find($threadId);
                    } catch (\Throwable $e) {
                        throw new \RuntimeException('[ACK_STEP]thread_find_failed', 0, $e);
                    }
                    if ($thread) {
                        try {
                            $reports = Report::where('thread_id', $thread->thread_id)
                                ->whereNull('approved_at')
                                ->orderBy('created_at', 'asc')
                                ->get();
                        } catch (\Throwable $e) {
                            throw new \RuntimeException('[ACK_STEP]report_query_failed', 0, $e);
                        }

                    // 通報理由（重複除外・各行「・」）
                    $reasonsText = $this->formatReportReasonsBulletList($reports->pluck('reason')->filter()->unique()->values()->all());

                        // 管理者承認と同様に、同一対象の複数理由は合算せず最大アウト値1件のみ加算。
                        $result['approved_reports'] += $this->approveReportsWithHighestOutCountOnly($reports, $selfAckMultiplier);

                    // 通報者へ通知（文言のみ「作成者が了承」に差し替え）
                    $rank = 1;
                    foreach ($reports as $rep) {
                        if ($rep->user_id) {
                            $threadTitleForReporter = $this->translateThreadTitleForUser($thread, (int) $rep->user_id);
                            $this->sendSelfAcknowledgeApprovalMessage((int) $rep->user_id, 'thread', $threadTitleForReporter, null, $rank);
                            $rank++;
                        }
                    }

                    // 被通報者（作成者）へ通知（文言のみ「作成者が了承」に差し替え）
                    if ($thread->user_id) {
                        $this->sendSelfAcknowledgeDeletionNotice((int) $thread->user_id, 'thread', (string) $thread->title, null, $reasonsText);
                    }

                        // ルーム削除（管理承認と同様にソフトデリート）
                        if (!$thread->trashed()) {
                            try {
                                $thread->delete();
                            } catch (\Throwable $e) {
                                throw new \RuntimeException('[ACK_STEP]thread_delete_failed', 0, $e);
                            }
                        }
                        Cache::forget('thread_restriction_' . $thread->thread_id);
                    }
                } elseif ($type === 'response' && $responseId) {
                    try {
                        $response = Response::find($responseId);
                    } catch (\Throwable $e) {
                        throw new \RuntimeException('[ACK_STEP]response_find_failed', 0, $e);
                    }
                    if ($response) {
                        try {
                            $reports = Report::where('response_id', $response->response_id)
                                ->whereNull('approved_at')
                                ->orderBy('created_at', 'asc')
                                ->get();
                        } catch (\Throwable $e) {
                            throw new \RuntimeException('[ACK_STEP]report_query_failed', 0, $e);
                        }

                    $threadTitleForOwner = $this->translateThreadTitleForUser($response->thread, (int) $response->user_id);
                    $responseBodyForOwner = $this->responseSnippetByRule($response, (int) $response->user_id, true);
                    $reasonsText = $this->formatReportReasonsBulletList($reports->pluck('reason')->filter()->unique()->values()->all());

                        $result['approved_reports'] += $this->approveReportsWithHighestOutCountOnly($reports, $selfAckMultiplier);

                    // 通報者へ通知（文言のみ「作成者が了承」に差し替え）
                    $rank = 1;
                    foreach ($reports as $rep) {
                        if ($rep->user_id) {
                            $reporterId = (int) $rep->user_id;
                            $threadTitleForReporter = $this->translateThreadTitleForUser($response->thread, $reporterId);
                            $responseBodyForReporter = $this->responseSnippetByRule($response, $reporterId, false);
                            $this->sendSelfAcknowledgeApprovalMessage($reporterId, 'response', $threadTitleForReporter, $responseBodyForReporter, $rank);
                            $rank++;
                        }
                    }

                    // 被通報者（作成者）へ通知（文言のみ「作成者が了承」に差し替え）
                    if ($response->user_id) {
                        $this->sendSelfAcknowledgeDeletionNotice((int) $response->user_id, 'response', $threadTitleForOwner, $responseBodyForOwner, $reasonsText);
                    }

                        // 仕様: 管理者画面の承認と同じ（レスポンスは削除しない）
                    }
                }

                // 実DBの状態で結果を確定（「成功表示だが実際は未反映」を防ぐ）
                try {
                    if ($type === 'thread' && $threadId) {
                        // Eloquent のスコープ/接続差分を避けるため生クエリで検証
                        $deletedAt = DB::table('threads')->where('thread_id', $threadId)->value('deleted_at');
                        $result['deleted_thread'] = $deletedAt !== null;
                        $result['approved_reports'] = (int) DB::table('reports')
                            ->where('thread_id', $threadId)
                            ->whereNotNull('approved_at')
                            ->count();
                    } elseif ($type === 'response' && $responseId) {
                        $result['deleted_response'] = !DB::table('responses')->where('response_id', $responseId)->exists();
                        $result['approved_reports'] = (int) DB::table('reports')
                            ->where('response_id', $responseId)
                            ->whereNotNull('approved_at')
                            ->count();
                    }
                } catch (\Throwable $e) {
                    if ($e instanceof \Illuminate\Database\QueryException) {
                        $sqlState = $e->errorInfo[0] ?? null;
                        $code = $e->errorInfo[1] ?? null;
                        $step = '[ACK_STEP]post_verify_db'
                            . ($sqlState ? " sqlstate={$sqlState}" : '')
                            . ($code !== null ? " code={$code}" : '');
                        throw new \RuntimeException($step, 0, $e);
                    }
                    throw new \RuntimeException('[ACK_STEP]post_verify_failed', 0, $e);
                }

            // ここまでで何もできていないなら成功扱いにしない（「受け付けたが何も起きない」を防ぐ）
            if ($result['approved_reports'] === 0 && !$result['deleted_thread'] && !$result['deleted_response']) {
                throw new \RuntimeException('[ACK_STEP]no_target_action');
            }

            // 制限を了承済みに
            // 本番DBのスキーマ差分に対応（存在するカラムのみ更新）
            $hasRestrictionsStatus = false;
            $hasRestrictionsAckAt = false;
            if ($restriction && $hasRestrictionsTable) {
                try {
                    $hasRestrictionsStatus = Schema::hasColumn('report_restrictions', 'status');
                } catch (\Throwable $e) {
                    $hasRestrictionsStatus = false;
                }
                try {
                    $hasRestrictionsAckAt = Schema::hasColumn('report_restrictions', 'acknowledged_at');
                } catch (\Throwable $e) {
                    $hasRestrictionsAckAt = false;
                }
            }

            if ($restriction && $hasRestrictionsTable && $hasRestrictionsStatus) {
                $restriction->status = 'acknowledged';
            }
            if ($restriction && $hasRestrictionsTable && $hasRestrictionsAckAt) {
                $restriction->acknowledged_at = now();
            }
            try {
                if ($restriction) {
                    $restriction->save();
                }
            } catch (\Throwable $e) {
                // 本番DBでマイグレーション未反映/制約差分がある場合のフォールバック
                // ここで処理全体を止めない（削除/通報承認を優先）
                try {
                    if ($hasRestrictionsTable) {
                        // まずは「非active化」さえできればよい（active判定から外す）
                        if ($hasRestrictionsStatus) {
                            DB::table('report_restrictions')
                                ->where('id', $restriction->id)
                                ->update([
                                    'status' => 'cleared',
                                    'updated_at' => now(),
                                ]);
                        } else {
                            // status が無い場合は削除で対応
                            DB::table('report_restrictions')->where('id', $restriction->id)->delete();
                        }
                    }
                } catch (\Throwable $ignored) {
                    \Log::warning('ReportRestriction save failed (ignored)', [
                        'restriction_id' => $restriction->id ?? null,
                        'error' => $e->getMessage(),
                        'fallback_error' => $ignored->getMessage(),
                    ]);
                }
            }

            // メッセージを処理済みに
            $message->reply_used = true;
            try {
                $message->save();
            } catch (\Throwable $e) {
                // 本番DBのスキーマ差分/制約差分があっても、処理自体は完走させる
                try {
                    if (Schema::hasTable('admin_messages') && Schema::hasColumn('admin_messages', 'reply_used')) {
                        DB::table('admin_messages')
                            ->where('id', $message->id)
                            ->update([
                                'reply_used' => true,
                                'updated_at' => now(),
                            ]);
                    }
                } catch (\Throwable $ignored) {
                    \Log::warning('AdminMessage reply_used update failed (ignored)', [
                        'admin_message_id' => $message->id ?? null,
                        'error' => $e->getMessage(),
                        'fallback_error' => $ignored->getMessage(),
                    ]);
                }
            }

            // 凍結判定（アウト数に基づく既存ロジック）
            try {
                $this->userOutCountFreezeService->processOutCountAndFreeze($user);
            } catch (\Throwable $e) {
                \Log::warning('processOutCountAndFreeze failed (ignored)', [
                    'user_id' => $user->user_id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }

            // 同時制限5件以上なら一時凍結
            try {
                $this->applyRestrictionCountFreezeIfNeeded($user);
            } catch (\Throwable $e) {
                \Log::warning('applyRestrictionCountFreezeIfNeeded failed (ignored)', [
                    'user_id' => $user->user_id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
            } catch (\Throwable $e) {
                if ($e instanceof \RuntimeException && str_starts_with((string) $e->getMessage(), '[ACK_STEP]')) {
                    throw $e;
                }
                if ($e instanceof QueryException) {
                    // SQLSTATE/ドライバメッセージを step に付けて原因特定を容易にする
                    $sqlState = $e->errorInfo[0] ?? null;
                    $code = $e->errorInfo[1] ?? null;
                    $step = '[ACK_STEP]db_query_exception'
                        . ($sqlState ? " sqlstate={$sqlState}" : '')
                        . ($code !== null ? " code={$code}" : '');
                    throw new \RuntimeException($step, 0, $e);
                }
                $cls = get_class($e);
                throw new \RuntimeException('[ACK_STEP]unhandled ' . $cls, 0, $e);
            }

            return $result;
        });
    }

    /**
     * mbstring の有無に依存しない安全な文字詰め
     */
    private function safeTrim(string $text, int $limit, string $suffix = '…'): string
    {
        $text = trim($text);
        if ($text === '') {
            return '';
        }
        if (function_exists('mb_strimwidth')) {
            return (string) mb_strimwidth($text, 0, $limit, $suffix);
        }
        if (function_exists('mb_substr')) {
            $t = (string) mb_substr($text, 0, $limit);
            return strlen($text) > strlen($t) ? ($t . $suffix) : $t;
        }
        $t = substr($text, 0, $limit);
        return strlen($text) > strlen($t) ? ($t . $suffix) : $t;
    }

    /**
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
     * @param  string  $type  'thread' | 'response' | 'profile'
     */
    private function formatReporterTargetBlock(string $type, string $threadTitle, ?string $responseBody, string $lang = 'JA'): string
    {
        $isEn = strtoupper($lang) === 'EN';
        if ($type === 'thread') {
            return ($isEn ? 'Room Name:' : 'ルーム名：') . "\n" . $threadTitle;
        }
        if ($type === 'response') {
            $snippet = mb_substr(strip_tags((string) ($responseBody ?? '')), 0, 2000);
            return ($isEn ? 'Room Name:' : 'ルーム名：') . "\n" . $threadTitle . "\n" . ($isEn ? 'Reply:' : 'リプライ内容：') . "\n" . $snippet;
        }

        return ($isEn ? 'Username:' : 'ユーザー名：') . "\n" . $threadTitle;
    }

    private function applyRestrictionCountFreezeIfNeeded(User $user): void
    {
        $threshold = (int) config('report_restrictions.freeze_threshold', 5);
        $activeCount = $this->activeRestrictionCount((int) $user->user_id);
        if ($activeCount < $threshold) {
            return;
        }
        if ($user->is_permanently_banned) {
            return;
        }
        $hours = (int) config('report_restrictions.freeze_duration_hours', 24);
        $until = now()->addHours($hours);
        if ($user->frozen_until && $user->frozen_until->isFuture() && $user->frozen_until->gte($until)) {
            return;
        }
        $wasFutureFrozen = $user->frozen_until && $user->frozen_until->isFuture();
        $user->frozen_until = $until;
        if (!$wasFutureFrozen) {
            $user->freeze_period_started_at = now();
        }
        $user->save();
        try {
            $user->logFreeze($until, '通報制限を同時に5件以上受けているため一時凍結');
        } catch (\Throwable $e) {
            \Log::warning('UserChangeLog::logFreeze failed (restriction count freeze)', [
                'user_id' => $user->user_id ?? null,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 同一対象の承認通報は、最大アウト値の1件のみ加算し、他は0として承認する。
     *
     * @param \Illuminate\Support\Collection<int, \App\Models\Report> $reports
     * @return int 承認した通報件数
     */
    private function approveReportsWithHighestOutCountOnly(\Illuminate\Support\Collection $reports, float $multiplier = 1.0): int
    {
        if ($reports->isEmpty()) {
            return 0;
        }

        $maxOutCount = 0.0;
        $maxReportId = null;
        foreach ($reports as $report) {
            $reason = (string) ($report->reason ?? '');
            $base = (float) ($report->out_count ?: Report::getDefaultOutCount($reason !== '' ? $reason : 'その他'));
            $candidate = round($base * $multiplier, 1);
            if ($candidate > $maxOutCount) {
                $maxOutCount = $candidate;
                $maxReportId = $report->report_id;
            }
        }

        $approvedAt = now();
        $approvedCount = 0;
        foreach ($reports as $report) {
            $report->out_count = ($maxReportId !== null && $report->report_id === $maxReportId) ? $maxOutCount : 0.0;
            $report->is_approved = true;
            $report->approved_at = $approvedAt;
            try {
                $report->save();
                $approvedCount++;
            } catch (\Throwable $e) {
                throw new \RuntimeException('[ACK_STEP]report_save_failed', 0, $e);
            }
        }

        return $approvedCount;
    }

    /**
     * 作成者が了承して削除した場合の「通報者」向け通知
     * 管理者承認と同様に順位に応じたコイン付与ロジックを踏襲する（文言のみ差し替え）
     */
    private function sendSelfAcknowledgeApprovalMessage(int $userId, string $type, string $threadTitle, ?string $responseBody, int $rank = 1): void
    {
        try {
            $lang = $this->getUserLanguageCode($userId);
            $isEn = $lang === 'EN';
            $contentType = match ($type) {
                'thread' => $isEn ? 'room' : 'ルーム',
                'response' => $isEn ? 'reply' : 'リプライ',
                default => $isEn ? 'profile' : 'プロフィール',
            };
            $content = $this->formatReporterTargetBlock($type, $threadTitle, $responseBody, $lang);

            $body = $isEn
                ? "The creator accepted your report and deleted the {$contentType} below.\n\n{$content}\n\nThank you for your cooperation."
                : "下記の{$contentType}において、作成者が通報内容を受け入れ、了承して削除しました。\n\n{$content}\n\nご協力ありがとうございました。";

            // ここもDBアクセスがあるため例外は握る（本処理を止めない）
            $userScore = (float) Report::calculateUserReportScore($userId);
            $coinAmount = $this->calculateCoinAmount($rank, $userScore);

            AdminMessage::create([
                'title' => $isEn ? 'Update on Your Report' : '通報内容の対応について',
                'body' => $body,
                'audience' => 'members',
                'user_id' => $userId,
                'published_at' => now(),
                'allows_reply' => false,
                'reply_used' => false,
                'coin_amount' => $coinAmount,
                'is_auto_sent' => true,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('sendSelfAcknowledgeApprovalMessage failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 作成者が了承して削除した場合の「被通報者（作成者）」向け通知
     */
    private function sendSelfAcknowledgeDeletionNotice(int $userId, string $type, string $threadTitle, ?string $responseBody, string $reasons): void
    {
        try {
            $lang = $this->getUserLanguageCode($userId);
            $isEn = $lang === 'EN';
            $content = $this->formatReporterTargetBlock($type, $threadTitle, $responseBody, $lang);
            $reasonsBlock = $isEn ? "[Reason for Report]\n" . str_replace('・', '- ', $reasons) . "\n\n" : "【通報理由】\n{$reasons}\n\n";
            if ($type === 'thread') {
                $body = $isEn
                    ? "You accepted the report for the room below, and the deletion process has been completed.\n\n[Reported Content]\n{$content}\n\n{$reasonsBlock}*This action completed processing without waiting for review."
                    : "あなたが通報内容を了承した下記のルームについて、削除処理を完了しました。\n\n【通報対象】\n{$content}\n\n{$reasonsBlock}※本操作により審査を待たずに処理が完了しました。";
            } elseif ($type === 'response') {
                $body = $isEn
                    ? "You accepted the report for the reply below, and the deletion process has been completed.\n\n[Reported Content]\n{$content}\n\n{$reasonsBlock}*This action completed processing without waiting for review."
                    : "あなたが通報内容を了承した下記のリプライについて、削除処理を完了しました。\n\n【通報対象】\n{$content}\n\n{$reasonsBlock}※本操作により審査を待たずに処理が完了しました。";
            } else {
                $body = $isEn
                    ? "You accepted the report for the profile below, and the deletion process has been completed.\n\n[Reported Content]\n{$content}\n\n{$reasonsBlock}*This action completed processing without waiting for review."
                    : "あなたが通報内容を了承した下記のプロフィールについて、削除処理を完了しました。\n\n【通報対象】\n{$content}\n\n{$reasonsBlock}※本操作により審査を待たずに処理が完了しました。";
            }

            AdminMessage::create([
                'title' => $isEn ? 'Deletion Completed' : '削除処理完了のお知らせ',
                'body' => $body,
                'audience' => 'members',
                'user_id' => $userId,
                'published_at' => now(),
                'allows_reply' => false,
                'reply_used' => false,
                'is_auto_sent' => true,
            ]);
        } catch (\Throwable $e) {
            \Log::warning('sendSelfAcknowledgeDeletionNotice failed', [
                'user_id' => $userId,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * 通報順位とスコアに基づいてコイン数を計算（AdminController の実装を踏襲）
     */
    private function calculateCoinAmount(int $rank, float $userScore): int
    {
        if ($rank > 5) {
            return 0;
        }
        if ($rank <= 3) {
            if ($userScore >= 0.7 && $userScore <= 0.8) {
                return 5;
            } elseif ($userScore >= 0.5 && $userScore < 0.7) {
                return 4;
            } elseif ($userScore >= 0.3 && $userScore < 0.5) {
                return 3;
            }
        }
        if ($rank >= 4 && $rank <= 5) {
            if ($userScore >= 0.7 && $userScore <= 0.8) {
                return 3;
            } elseif ($userScore >= 0.5 && $userScore < 0.7) {
                return 2;
            } elseif ($userScore >= 0.3 && $userScore < 0.5) {
                return 1;
            }
        }
        return 0;
    }

    /**
     * @param array $attrs Restriction create attrs (must include type,user_id)
     * @param callable():AdminMessage $createMessage
     */
    private function firstOrCreateActive(array $attrs, callable $createMessage): ?ReportRestriction
    {
        return DB::transaction(function () use ($attrs, $createMessage) {
            $query = ReportRestriction::query()
                ->where('status', 'active')
                ->where('type', $attrs['type'])
                ->where('user_id', $attrs['user_id']);

            foreach (['thread_id', 'response_id', 'reported_user_id'] as $k) {
                if (array_key_exists($k, $attrs)) {
                    $query->where($k, $attrs[$k]);
                } else {
                    $query->whereNull($k);
                }
            }

            $existing = $query->lockForUpdate()->first();
            if ($existing) {
                return $existing;
            }

            $message = $createMessage();
            $attrs['admin_message_id'] = $message?->id;

            $created = ReportRestriction::create($attrs);

            // 同時制限5件以上なら一時凍結（制限が発生した瞬間にも適用）
            $u = User::find($attrs['user_id']);
            if ($u) {
                $this->applyRestrictionCountFreezeIfNeeded($u);
            }

            return $created;
        });
    }

    /**
     * 通報制限通知の本文を組み立てる（仕様: 通報対象の審査について）
     *
     * @param string $type 'thread' | 'response'
     * @param string $roomTitle ルーム名
     * @param string|null $replySnippet リプライ本文の抜粋（response の場合のみ・任意）
     * @param array<int, string> $reasons 通報理由のリスト
     */
    private function buildRestrictionNoticeBody(string $type, string $roomTitle, ?string $replySnippet, array $reasons, string $lang = 'JA'): string
    {
        $isEn = strtoupper($lang) === 'EN';
        $contentType = $type === 'thread' ? ($isEn ? 'room' : 'ルーム') : ($isEn ? 'reply' : 'リプライ');

        $parts = [];
        $parts[] = $isEn
            ? "Your {$contentType} below has been reported and is currently under review for potential violations."
            : "現在、あなたの作成した以下の{$contentType}は通報を受け、違反の有無について審査中です。";
        $parts[] = '';
        $parts[] = $isEn ? '[Reported Content]' : '【通報対象】';

        $contentLines = [$isEn ? 'Room Name:' : 'ルーム名：', $roomTitle];
        if ($type === 'response') {
            $contentLines[] = $isEn ? 'Reply:' : 'リプライ内容：';
            $contentLines[] = (string) $replySnippet;
        }
        $parts[] = implode("\n", $contentLines);
        $parts[] = '';

        $parts[] = $isEn ? '[Reason for Report]' : '【通報理由】';
        foreach ($reasons as $r) {
            if ((string) $r !== '') {
                $parts[] = $isEn ? '- ' . $r : '・' . $r;
            }
        }
        $parts[] = '';

        if ($isEn) {
            $parts[] = 'Some features are restricted during the review.';
            $parts[] = 'If you acknowledge and accept the report using the button below, the content will be deleted and processing will complete without waiting for review.';
            $parts[] = '*If no action is taken, the review will continue.';
        } else {
            $parts[] = '審査中は一部機能の利用が制限されます。';
            $parts[] = 'なお、通報内容を以下のボタンから了承して受け入れる場合は、該当投稿は削除され、審査を待たずに処理が完了します。';
            $parts[] = '※本操作を行わない場合、審査は継続されます。';
        }

        return implode("\n", $parts);
    }

    /** @return array<int, string> */
    private function getReportReasonsForThread(int $threadId): array
    {
        return Report::where('thread_id', $threadId)
            ->whereNotNull('reason')
            ->where('reason', '!=', '')
            ->pluck('reason')
            ->unique()
            ->values()
            ->all();
    }

    /** @return array<int, string> */
    private function getReportReasonsForResponse(int $responseId): array
    {
        return Report::where('response_id', $responseId)
            ->whereNotNull('reason')
            ->where('reason', '!=', '')
            ->pluck('reason')
            ->unique()
            ->values()
            ->all();
    }

    /**
     * R18変更提案に該当する通報理由（成人向けコンテンツ）で、未処理通報が残っているか判定
     */
    private function hasAdultContentReasonPending(?string $type, ?int $threadId, ?int $responseId): bool
    {
        $reason = '成人向けコンテンツが含まれる';
        if ($type === 'thread' && $threadId) {
            return Report::where('thread_id', $threadId)
                ->where('reason', $reason)
                ->whereNull('approved_at')
                ->exists();
        }
        if ($type === 'response' && $responseId) {
            return Report::where('response_id', $responseId)
                ->where('reason', $reason)
                ->whereNull('approved_at')
                ->exists();
        }
        return false;
    }

    /**
     * 対象の通報処理状態を取得
     *
     * @return array{pending:int, processed:int}
     */
    private function getReportProcessingState(?string $type, ?int $threadId, ?int $responseId): array
    {
        if ($type === 'thread' && $threadId) {
            $base = Report::where('thread_id', $threadId);
        } elseif ($type === 'response' && $responseId) {
            $base = Report::where('response_id', $responseId);
        } else {
            return ['pending' => 0, 'processed' => 0];
        }

        $pending = (clone $base)->whereNull('approved_at')->count();
        $processed = (clone $base)->whereNotNull('approved_at')->count();

        return [
            'pending' => (int) $pending,
            'processed' => (int) $processed,
        ];
    }

    private function sendRestrictionAckMessage(array $data): AdminMessage
    {
        return AdminMessage::create([
            'title_key' => $data['title_key'] ?? null,
            'body_key' => $data['body_key'] ?? null,
            'title' => null,
            'body' => $data['body'] ?? '',
            'audience' => 'members',
            'user_id' => $data['user_id'],
            'thread_id' => $data['thread_id'] ?? null,
            'response_id' => $data['response_id'] ?? null,
            'reported_user_id' => $data['reported_user_id'] ?? null,
            'published_at' => now(),
            'allows_reply' => false,
            'unlimited_reply' => false,
            'reply_used' => false,
            'is_auto_sent' => true,
        ]);
    }

    /**
     * 「成人向けコンテンツが含まれる」通報がある場合に、スレッド作成者へR18変更リクエストを送信
     */
    private function sendR18ChangeRequestIfNeeded(Thread $thread): void
    {
        $threadCreator = $thread->user;
        if (!$threadCreator || !$threadCreator->isAdult()) {
            return;
        }

        $hasAdultContentReport = Report::where(function ($q) use ($thread) {
                $q->where('thread_id', $thread->thread_id)
                    ->orWhereIn('response_id', function ($sub) use ($thread) {
                        $sub->select('response_id')
                            ->from('responses')
                            ->where('thread_id', $thread->thread_id);
                    });
            })
            ->where('reason', '成人向けコンテンツが含まれる')
            ->where(function ($q) {
                $q->where('is_approved', true)
                    ->orWhereNull('approved_at');
            })
            ->exists();

        if (!$hasAdultContentReport) {
            return;
        }

        $alreadySent = AdminMessage::where('thread_id', $thread->thread_id)
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

        AdminMessage::create([
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

