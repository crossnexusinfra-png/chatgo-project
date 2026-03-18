<?php

namespace App\Services;

use App\Models\AdminMessage;
use App\Models\ReportRestriction;
use App\Models\Thread;
use App\Models\Response;
use App\Models\User;
use Illuminate\Support\Facades\DB;

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
            return $this->sendRestrictionAckMessage([
                'user_id' => (int) $thread->user_id,
                'thread_id' => (int) $thread->thread_id,
                'title_key' => 'report_restriction_ack_title',
                'body_key' => null,
                'body' => "あなたが作成したルーム「{$thread->title}」は、複数の通報により制限対象となりました。\n\n【重要】了承ボタンを押すと、このルーム（および関連するリプライ）が削除される場合があります。\n\n内容を確認のうえ、了承してください。",
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
            return $this->sendRestrictionAckMessage([
                'user_id' => (int) $response->user_id,
                'thread_id' => (int) $response->thread_id,
                'response_id' => (int) $response->response_id,
                'title_key' => 'report_restriction_ack_title',
                'body_key' => null,
                'body' => "あなたのリプライ（ルーム「{$threadTitle}」内）が、複数の通報により制限対象となりました。\n\n【重要】了承ボタンを押すと、このリプライが削除されます。\n\n内容を確認のうえ、了承してください。",
            ]);
        });
    }

    public function ensureRestrictionCreatedForProfile(User $user): ?ReportRestriction
    {
        if (!$user->shouldBeHidden()) {
            return null;
        }

        return $this->firstOrCreateActive([
            'type' => 'profile',
            'user_id' => (int) $user->user_id,
            'reported_user_id' => (int) $user->user_id,
        ], function () use ($user) {
            return $this->sendRestrictionAckMessage([
                'user_id' => (int) $user->user_id,
                'reported_user_id' => (int) $user->user_id,
                'title_key' => 'report_restriction_ack_title',
                'body_key' => null,
                'body' => "あなたのプロフィールが、複数の通報により制限対象となりました。\n\n【重要】了承ボタンを押すと、自己紹介文は無記載に戻り、一定期間変更できなくなります。\n\n内容を確認のうえ、了承してください。",
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

            return ReportRestriction::create($attrs);
        });
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

