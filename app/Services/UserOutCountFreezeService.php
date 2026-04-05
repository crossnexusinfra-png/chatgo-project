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
            if (!$wasBanned) {
                $user->freeze_period_started_at = now();
            }
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
                if (!$wasFrozen) {
                    $user->freeze_period_started_at = now();
                }
                $user->save();

                if (!$wasFrozen) {
                    $user->logFreeze($freezeDuration, 'アウト数が2以上に達したため一時凍結');
                    $this->sendFreezeNotice($user, $freezeDuration);
                }
            }
        } else {
            if ($outCount >= 1.0 && $outCount < 2.0) {
                $suppressMonths = max(1, (int) config('report_restrictions.out_warning_suppress_months', 1));
                $recentWarning = AdminMessage::where('user_id', $user->user_id)
                    ->where('title', '利用に関する警告')
                    ->where('created_at', '>=', now()->subMonths($suppressMonths))
                    ->exists();

                if (!$recentWarning) {
                    $this->sendWarningNotice($user);
                }
            }

            if ($outCount < 1.0 && $user->frozen_until) {
                $user->freeze_count = 0;
                $user->frozen_until = null;
                $user->freeze_period_started_at = null;
                $user->save();
                $user->logFreeze(null, 'アウト数が0になったため凍結解除');
            }
        }
    }

    private function sendWarningNotice(User $user): void
    {
        $bodyJa = "あなたの投稿について、違反行為が確認されました。\n今後、同様の行為を続けると、アカウントが凍結される可能性があります。利用規約を遵守した投稿をお願いいたします。";

        AdminMessage::create([
            'title' => '利用に関する警告',
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
        $bodyJa = "あなたのアカウントが一時的に凍結されました。\n\n凍結解除予定日時: {$freezeUntilFormatted}\n\n凍結期間中は、閲覧以外の操作はできません。\n今後は利用規約を遵守した投稿をお願いいたします。";

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
        $bodyJa = "あなたのアカウントが永久凍結されました。\n今後、このアカウントでログインすることはできますが、閲覧以外の操作はできません。また、同じメールアドレスおよび電話番号での新規登録もできません。";

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
