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

class ReportRestrictionService
{
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
            $body = $this->buildRestrictionNoticeBody('thread', $thread->title, null, $reasons);
            return $this->sendRestrictionAckMessage([
                'user_id' => (int) $thread->user_id,
                'thread_id' => (int) $thread->thread_id,
                'title_key' => 'report_restriction_review_title',
                'body_key' => null,
                'body' => $body,
            ]);
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
            $threadTitle = $response->thread?->title ?? '（タイトルなし）';
            $replySnippet = $response->body ? mb_strimwidth(strip_tags($response->body), 0, 80, '…') : '';
            $reasons = $this->getReportReasonsForResponse($response->response_id);
            $body = $this->buildRestrictionNoticeBody('response', $threadTitle, $replySnippet, $reasons);
            return $this->sendRestrictionAckMessage([
                'user_id' => (int) $response->user_id,
                'thread_id' => (int) $response->thread_id,
                'response_id' => (int) $response->response_id,
                'title_key' => 'report_restriction_review_title',
                'body_key' => null,
                'body' => $body,
            ]);
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

    public function acknowledgeFromMessage(AdminMessage $message): void
    {
        DB::transaction(function () use ($message) {
            try {
                /** @var User|null $user */
                $user = User::find($message->user_id);
                if (!$user) {
                    throw new \RuntimeException('[ACK_STEP]user_not_found');
                }

                $restriction = ReportRestriction::where('admin_message_id', $message->id)
                    ->where('user_id', $user->user_id)
                    ->where('status', 'active')
                    ->lockForUpdate()
                    ->first();

                if (!$restriction) {
                    // 既に処理済み/存在しない場合は何もしない
                    return;
                }

                // 承認と同様に処理（ただし自認による軽減係数を適用）
                $selfAckMultiplier = (float) config('report_restrictions.self_ack_out_multiplier', 0.7);

                $type = $restriction->type;

                if ($type === 'thread' && $restriction->thread_id) {
                    $thread = Thread::withTrashed()->find($restriction->thread_id);
                    if ($thread) {
                        $reports = Report::where('thread_id', $thread->thread_id)
                            ->whereNull('approved_at')
                            ->orderBy('created_at', 'asc')
                            ->lockForUpdate()
                            ->get();

                    // 通報理由（重複除外）
                    $reasonsText = implode('、', $reports->pluck('reason')->filter()->unique()->values()->all());

                        // 管理者承認と同様に承認処理（アウト数のみ軽減）
                        foreach ($reports as $rep) {
                            $reason = (string) ($rep->reason ?? '');
                            $base = (float) ($rep->out_count ?: Report::getDefaultOutCount($reason !== '' ? $reason : 'その他'));
                            // out_count は DB 的に 0.5〜 を前提としている箇所があるため、軽減しても 0.5 未満にしない
                            $newOutCount = max(0.5, round($base * $selfAckMultiplier, 1));
                            $rep->out_count = $newOutCount;
                            $rep->is_approved = true;
                            $rep->approved_at = now();
                            try {
                                $rep->save();
                            } catch (\Throwable $e) {
                                throw new \RuntimeException('[ACK_STEP]report_save_failed', 0, $e);
                            }
                        }

                    // 通報者へ通知（文言のみ「作成者が了承」に差し替え）
                    $rank = 1;
                    foreach ($reports as $rep) {
                        if ($rep->user_id) {
                            $this->sendSelfAcknowledgeApprovalMessage((int) $rep->user_id, 'thread', (string) $thread->title, null, $rank);
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
                } elseif ($type === 'response' && $restriction->response_id) {
                    $response = Response::find($restriction->response_id);
                    if ($response) {
                        $reports = Report::where('response_id', $response->response_id)
                            ->whereNull('approved_at')
                            ->orderBy('created_at', 'asc')
                            ->lockForUpdate()
                            ->get();

                    $threadTitle = (string) ($response->thread?->title ?? '（タイトルなし）');
                    $responseBody = (string) ($response->body ?? '');
                    // 念のため長文で通知が落ちないように抑制
                    $responseBodyForMsg = mb_strimwidth($responseBody, 0, 500, '…');
                    $reasonsText = implode('、', $reports->pluck('reason')->filter()->unique()->values()->all());

                        foreach ($reports as $rep) {
                            $reason = (string) ($rep->reason ?? '');
                            $base = (float) ($rep->out_count ?: Report::getDefaultOutCount($reason !== '' ? $reason : 'その他'));
                            $newOutCount = max(0.5, round($base * $selfAckMultiplier, 1));
                            $rep->out_count = $newOutCount;
                            $rep->is_approved = true;
                            $rep->approved_at = now();
                            try {
                                $rep->save();
                            } catch (\Throwable $e) {
                                throw new \RuntimeException('[ACK_STEP]report_save_failed', 0, $e);
                            }
                        }

                    // 通報者へ通知（文言のみ「作成者が了承」に差し替え）
                    $rank = 1;
                    foreach ($reports as $rep) {
                        if ($rep->user_id) {
                            $this->sendSelfAcknowledgeApprovalMessage((int) $rep->user_id, 'response', $threadTitle, $responseBodyForMsg, $rank);
                            $rank++;
                        }
                    }

                    // 被通報者（作成者）へ通知（文言のみ「作成者が了承」に差し替え）
                    if ($response->user_id) {
                        $this->sendSelfAcknowledgeDeletionNotice((int) $response->user_id, 'response', $threadTitle, $responseBodyForMsg, $reasonsText);
                    }

                        // リプライ削除（現状ソフトデリート無しのため削除）
                        try {
                            $response->delete();
                        } catch (\Throwable $e) {
                            throw new \RuntimeException('[ACK_STEP]response_delete_failed', 0, $e);
                        }
                    }
                }

            // 制限を了承済みに
            // 本番DBのスキーマ差分に対応（存在するカラムのみ更新）
            if (Schema::hasTable('report_restrictions') && Schema::hasColumn('report_restrictions', 'status')) {
                $restriction->status = 'acknowledged';
            }
            if (Schema::hasTable('report_restrictions') && Schema::hasColumn('report_restrictions', 'acknowledged_at')) {
                $restriction->acknowledged_at = now();
            }
            try {
                $restriction->save();
            } catch (\Throwable $e) {
                // 本番DBでマイグレーション未反映/制約差分がある場合のフォールバック
                // ここで処理全体を止めない（削除/通報承認を優先）
                try {
                    if (Schema::hasTable('report_restrictions')) {
                        // まずは「非active化」さえできればよい（active判定から外す）
                        if (Schema::hasColumn('report_restrictions', 'status')) {
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
                throw new \RuntimeException('[ACK_STEP]message_save_failed', 0, $e);
            }

            // 凍結判定（アウト数に基づく既存ロジック）
            try {
                $this->applyOutCountFreezeIfNeeded($user);
            } catch (\Throwable $e) {
                throw new \RuntimeException('[ACK_STEP]apply_out_count_freeze_failed', 0, $e);
            }

            // 同時制限5件以上なら一時凍結
            try {
                $this->applyRestrictionCountFreezeIfNeeded($user);
            } catch (\Throwable $e) {
                throw new \RuntimeException('[ACK_STEP]apply_restriction_count_freeze_failed', 0, $e);
            }
            } catch (\Throwable $e) {
                if ($e instanceof \RuntimeException && str_starts_with((string) $e->getMessage(), '[ACK_STEP]')) {
                    throw $e;
                }
                throw new \RuntimeException('[ACK_STEP]unhandled', 0, $e);
            }
        });
    }

    private function applyOutCountFreezeIfNeeded(User $user): void
    {
        // AdminController の private 実装を簡易的に踏襲（同条件）
        \App\Models\Report::resetExpiredOutCounts();
        $outCount = $user->calculateOutCount();

        if ($user->shouldBePermanentlyBanned()) {
            $user->is_permanently_banned = true;
            $user->frozen_until = null;
            $user->save();
            return;
        }

        if ($user->shouldBeTemporarilyFrozen()) {
            $freezeUntil = $user->calculateFreezeDuration();
            if ($freezeUntil) {
                $wasFrozen = $user->frozen_until && $user->frozen_until->isFuture();
                $user->frozen_until = $freezeUntil;
                $user->freeze_count++;
                $user->save();
                if (!$wasFrozen) {
                    try {
                        $user->logFreeze($freezeUntil, '通報アウト数が2以上に達したため一時凍結');
                    } catch (\Throwable $e) {
                        \Log::warning('UserChangeLog::logFreeze failed (temp freeze)', [
                            'user_id' => $user->user_id ?? null,
                            'error' => $e->getMessage(),
                        ]);
                    }
                }
            }
        } else {
            if ($outCount < 1.0 && $user->frozen_until) {
                $user->freeze_count = 0;
                $user->frozen_until = null;
                $user->save();
                try {
                    $user->logFreeze(null, 'アウト数が0になったため凍結解除');
                } catch (\Throwable $e) {
                    \Log::warning('UserChangeLog::logFreeze failed (unfreeze)', [
                        'user_id' => $user->user_id ?? null,
                        'error' => $e->getMessage(),
                    ]);
                }
            }
        }
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
        $user->frozen_until = $until;
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
     * 作成者が了承して削除した場合の「通報者」向け通知
     * 管理者承認と同様に順位に応じたコイン付与ロジックを踏襲する（文言のみ差し替え）
     */
    private function sendSelfAcknowledgeApprovalMessage(int $userId, string $type, string $threadTitle, ?string $responseBody, int $rank = 1): void
    {
        $contentType = $type === 'thread' ? 'スレッド' : ($type === 'response' ? 'レスポンス' : 'プロフィール');
        $content = $type === 'thread'
            ? $threadTitle
            : ($type === 'response' ? $threadTitle . "\n\n" . ($responseBody ?? '') : $threadTitle);

        $bodyJa = "下記の{$contentType}において、作成者が通報内容を受け入れ、了承して削除しました。\n\n{$content}\n\nご協力ありがとうございました。";

        $userScore = Report::calculateUserReportScore($userId);
        $coinAmount = $this->calculateCoinAmount($rank, $userScore);

        try {
            AdminMessage::create([
                'title' => '通報内容対応完了のお知らせ',
                'body' => $bodyJa,
                'audience' => 'members',
                'user_id' => $userId,
                'published_at' => now(),
                'allows_reply' => false,
                'reply_used' => false,
                'coin_amount' => $coinAmount,
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
        $contentType = $type === 'thread' ? 'スレッド' : ($type === 'response' ? 'レスポンス' : 'プロフィール');
        $content = $type === 'thread'
            ? $threadTitle
            : ($type === 'response' ? $threadTitle . "\n\n" . ($responseBody ?? '') : $threadTitle);

        $reasonsBlock = $reasons !== '' ? "【通報理由】\n{$reasons}\n\n" : '';
        $bodyJa = "お客様が作成された{$contentType}について、通報を受けて審査中でしたが、作成者が通報内容を受け入れ、了承して削除しました。\n\n{$content}\n\n{$reasonsBlock}※本操作により審査を待たずに処理が完了しました。";

        try {
            AdminMessage::create([
                'title' => '削除処理完了のお知らせ',
                'body' => $bodyJa,
                'audience' => 'members',
                'user_id' => $userId,
                'published_at' => now(),
                'allows_reply' => false,
                'reply_used' => false,
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
     * @param string|null $replySnippet リプライ本文の抜粋（response の場合のみ）
     * @param array<int, string> $reasons 通報理由のリスト
     */
    private function buildRestrictionNoticeBody(string $type, string $roomTitle, ?string $replySnippet, array $reasons): string
    {
        $targetLabel = $type === 'thread' ? 'ルーム' : 'リプライ';
        $intro = "現在、あなたの作成した以下の（{$targetLabel}）は通報を受け、違反の有無について審査中です。\n\n";

        $contentLine = $roomTitle;
        if ($replySnippet !== null && $replySnippet !== '') {
            $contentLine .= "\n" . $replySnippet;
        }
        $intro .= $contentLine . "\n\n";

        $reasonBlock = count($reasons) > 0
            ? implode("\n", array_map(fn (string $r) => '・' . $r, $reasons)) . "\n\n"
            : '';

        $rest = "審査中は一部機能の利用が制限されます。\n\n";
        $rest .= "なお、通報内容を受け入れる場合は、以下の操作が可能です：\n\n";
        $rest .= "了承して削除する\n";
        $rest .= "　該当投稿は削除され、審査を待たずに処理が完了します。\n\n";
        $rest .= "※本操作を行わない場合、審査は継続されます。";

        return $intro . $reasonBlock . $rest;
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
        ]);
    }
}

