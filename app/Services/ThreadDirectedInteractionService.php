<?php

namespace App\Services;

use App\Models\Response;
use App\Models\Thread;
use App\Models\ThreadInteraction;
use App\Models\User;
use Carbon\Carbon;

/**
 * フレンド申請条件用: 同一ルーム内のユーザー間「会話」（厳密ペア）を集計し thread_interactions に保存する。
 */
class ThreadDirectedInteractionService
{
    private const TWELVE_HOURS_SECONDS = 43200;

    /**
     * 投稿者を含む、当該ルーム内の全ユーザーとの指向別統計を再計算して保存する。
     * （他ユーザーの投稿は A↔B のペア成立には影響しないが、再計算コスト削減のため投稿者周りのみ更新する。）
     */
    public function syncInteractionsForUserInThread(Thread $thread, User $user): void
    {
        $threadId = (int) $thread->thread_id;
        $responses = Response::query()
            ->where('thread_id', $threadId)
            ->whereNotNull('user_id')
            ->orderBy('responses_num')
            ->orderBy('response_id')
            ->get(['response_id', 'user_id', 'body', 'media_file', 'created_at']);

        if ($responses->isEmpty()) {
            return;
        }

        $otherIds = $responses->pluck('user_id')
            ->unique()
            ->filter(fn (int|string|null $id) => (int) $id !== (int) $user->user_id)
            ->values();

        foreach ($otherIds as $otherId) {
            $otherId = (int) $otherId;
            $ab = $this->computeDirectedStatsFromResponses($responses, (int) $user->user_id, $otherId);
            $ba = $this->computeDirectedStatsFromResponses($responses, $otherId, (int) $user->user_id);
            $this->persistDirected($threadId, (int) $user->user_id, $otherId, $ab);
            $this->persistDirected($threadId, $otherId, (int) $user->user_id, $ba);
        }
    }

    /**
     * @param  \Illuminate\Support\Collection<int, Response>  $responses
     * @return array{message_count: int, total_characters: int}
     */
    public function computeDirectedStatsFromResponses($responses, int $fromUserId, int $toUserId): array
    {
        $n = $responses->count();
        $count = 0;
        $chars = 0;

        for ($i = 0; $i < $n; $i++) {
            $ri = $responses[$i];
            if ((int) $ri->user_id !== $fromUserId) {
                continue;
            }
            $riTime = $this->asCarbon($ri->created_at);
            if (!$riTime) {
                continue;
            }

            for ($j = $i + 1; $j < $n; $j++) {
                $rj = $responses[$j];
                if ((int) $rj->user_id !== $toUserId) {
                    continue;
                }
                $rjTime = $this->asCarbon($rj->created_at);
                if (!$rjTime || $rjTime->lessThanOrEqualTo($riTime)) {
                    continue;
                }
                $delta = $riTime->diffInSeconds($rjTime);
                if ($delta > self::TWELVE_HOURS_SECONDS) {
                    continue;
                }

                $gapOk = true;
                for ($k = $i + 1; $k < $j; $k++) {
                    $uid = (int) $responses[$k]->user_id;
                    if ($uid === $fromUserId || $uid === $toUserId) {
                        $gapOk = false;
                        break;
                    }
                }
                if (!$gapOk) {
                    continue;
                }

                $count++;
                // A→B の「返し」とみなす B 側（toUser）の本文のみ。メディア付きは 5 文字換算。
                $chars += $this->responseCharacterContributionForFriendStats($rj);
            }
        }

        return [
            'message_count' => $count,
            'total_characters' => $chars,
        ];
    }

    /**
     * フレンド条件用の 1 リプライあたりの文字数（本文 + メディアあれば 5）
     */
    private function responseCharacterContributionForFriendStats(Response $response): int
    {
        $bodyLen = mb_strlen((string) ($response->body ?? ''));
        $mediaBonus = ! empty($response->media_file) ? 5 : 0;

        return $bodyLen + $mediaBonus;
    }

    /**
     * @param  array{message_count: int, total_characters: int}  $stats
     */
    private function persistDirected(int $threadId, int $userId, int $otherUserId, array $stats): void
    {
        if ($stats['message_count'] === 0 && $stats['total_characters'] === 0) {
            ThreadInteraction::query()
                ->where('thread_id', $threadId)
                ->where('user_id', $userId)
                ->where('other_user_id', $otherUserId)
                ->delete();

            return;
        }

        ThreadInteraction::updateOrCreate(
            [
                'thread_id' => $threadId,
                'user_id' => $userId,
                'other_user_id' => $otherUserId,
            ],
            [
                'message_count' => $stats['message_count'],
                'total_characters' => $stats['total_characters'],
                'last_interaction_at' => now(),
            ]
        );
    }

    private function asCarbon(mixed $value): ?Carbon
    {
        if ($value instanceof Carbon) {
            return $value;
        }
        if ($value === null) {
            return null;
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
