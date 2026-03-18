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
            /** @var User|null $user */
            $user = User::find($message->user_id);
            if (!$user) {
                throw new \RuntimeException('User not found');
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
                    $reports = \App\Models\Report::where('thread_id', $thread->thread_id)
                        ->whereNull('approved_at')
                        ->lockForUpdate()
                        ->get();
                    foreach ($reports as $rep) {
                        $reason = (string) ($rep->reason ?? '');
                        $base = $rep->out_count ?: Report::getDefaultOutCount($reason !== '' ? $reason : 'その他');
                        $rep->out_count = $base * $selfAckMultiplier;
                        $rep->is_approved = true;
                        $rep->approved_at = now();
                        $rep->save();
                    }
                    // ルーム削除（管理承認と同様にソフトデリート）
                    if (!$thread->trashed()) {
                        $thread->delete();
                    }
                    Cache::forget('thread_restriction_' . $thread->thread_id);
                }
            } elseif ($type === 'response' && $restriction->response_id) {
                $response = Response::find($restriction->response_id);
                if ($response) {
                    $reports = \App\Models\Report::where('response_id', $response->response_id)
                        ->whereNull('approved_at')
                        ->lockForUpdate()
                        ->get();
                    foreach ($reports as $rep) {
                        $reason = (string) ($rep->reason ?? '');
                        $base = $rep->out_count ?: Report::getDefaultOutCount($reason !== '' ? $reason : 'その他');
                        $rep->out_count = $base * $selfAckMultiplier;
                        $rep->is_approved = true;
                        $rep->approved_at = now();
                        $rep->save();
                    }
                    // リプライ削除（現状ソフトデリート無しのため削除）
                    $response->delete();
                }
            }

            // 制限を了承済みに
            $restriction->status = 'acknowledged';
            $restriction->acknowledged_at = now();
            $restriction->save();

            // メッセージを処理済みに
            $message->reply_used = true;
            $message->save();

            // 凍結判定（アウト数に基づく既存ロジック）
            $this->applyOutCountFreezeIfNeeded($user);

            // 同時制限5件以上なら一時凍結
            $this->applyRestrictionCountFreezeIfNeeded($user);
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
                    $user->logFreeze($freezeUntil, '通報アウト数が2以上に達したため一時凍結');
                }
            }
        } else {
            if ($outCount < 1.0 && $user->frozen_until) {
                $user->freeze_count = 0;
                $user->frozen_until = null;
                $user->save();
                $user->logFreeze(null, 'アウト数が0になったため凍結解除');
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
        $user->logFreeze($until, '通報制限を同時に5件以上受けているため一時凍結');
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

