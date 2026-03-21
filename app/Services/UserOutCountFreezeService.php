<?php

namespace App\Services;

use App\Models\AdminMessage;
use App\Models\Report;
use App\Models\User;

/**
 * 承認済み通報に基づくアウト数・凍結・お知らせを一括処理する。
 * 管理者承認（AdminController）と本人了承（ReportRestrictionService）で同一ロジックを共有する。
 */
class UserOutCountFreezeService
{
    public function processOutCountAndFreeze(User $user): void
    {
        Report::resetExpiredOutCounts();

        $outCount = $user->calculateOutCount();

        if ($user->shouldBePermanentlyBanned()) {
            $wasBanned = $user->is_permanently_banned;
            $user->is_permanently_banned = true;
            $user->frozen_until = null;
            $user->save();

            if (!$wasBanned) {
                $user->logPermanentBan('アウト数が4以上に達したため永久凍結');
                $this->sendPermanentBanNotice($user);
            }

            return;
        }

        if ($user->shouldBeTemporarilyFrozen()) {
            $wasFrozen = $user->frozen_until && $user->frozen_until->isFuture();
            $freezeDuration = $user->calculateFreezeDuration();
            if ($freezeDuration) {
                $user->frozen_until = $freezeDuration;
                $user->freeze_count++;
                $user->save();

                if (!$wasFrozen) {
                    $user->logFreeze($freezeDuration, 'アウト数が2以上に達したため一時凍結');
                    $this->sendFreezeNotice($user, $freezeDuration);
                }
            }
        } else {
            if ($outCount >= 1.0 && $outCount < 2.0) {
                $recentWarning = AdminMessage::where('user_id', $user->user_id)
                    ->where('title', 'アウト警告のお知らせ')
                    ->where('created_at', '>=', now()->subWeek())
                    ->exists();

                if (!$recentWarning) {
                    $this->sendWarningNotice($user);
                }
            }

            if ($outCount < 1.0 && $user->frozen_until) {
                $user->freeze_count = 0;
                $user->frozen_until = null;
                $user->save();
                $user->logFreeze(null, 'アウト数が0になったため凍結解除');
            }
        }
    }

    private function sendWarningNotice(User $user): void
    {
        $bodyJa = "お客様の投稿について、通報が承認されました。現在、アウト数が1に達しています。\n\n今後、同様の行為を続けると、アカウントが一時凍結または永久凍結される可能性があります。利用規約を遵守した投稿をお願いいたします。";

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

    private function sendFreezeNotice(User $user, \Carbon\Carbon $freezeUntil): void
    {
        $freezeUntilFormatted = $freezeUntil->format('Y年m月d日 H:i');
        $bodyJa = "お客様のアカウントが一時凍結されました。\n\n凍結解除予定日時: {$freezeUntilFormatted}\n\n凍結期間中は、閲覧以外の操作（スレッド・レスポンスの投稿、プロフィール編集、コイン獲得送信など）ができません。\n\n今後は利用規約を遵守した投稿をお願いいたします。";

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

    private function sendPermanentBanNotice(User $user): void
    {
        $bodyJa = "お客様のアカウントが永久凍結されました。\n\n今後、このアカウントでログインすることはできますが、ログアウト以外の操作は一切できません。また、同じメールアドレスおよび電話番号での新規登録もできません。";

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
}
