<?php

namespace App\Services;

use App\Models\AdminMessage;
use App\Models\Report;
use App\Models\User;

/**
 * 承認済み通報に基づくアウト数・凍結・お知らせを一括処理する。
 * 管理者承認（AdminController）と本人了承（ReportRestrictionService）で同一ロジックを共有する。
 *
 * @param  bool  $afterFreezeAppealApproval  true のとき、減算直後の一時凍結の再適用を行わず、
 *                                          一時凍結を期限切れ相当で終了し freeze_count を 1 減らす（下限 0）。
 *                                          この分岐では「利用に関する警告」は送らない。
 *
 * 一時凍結・利用に関する警告は、前回処理時の `sanctions_out_count_snapshot` と比較し
 * **アウト数が増加したときのみ** 適用する（減算・自動の時効などで下がった場合は新規に発動しない）。
 * 永久凍結（4アウト以上）は通常どおり適用するが、**異議申し立て承認**（`afterFreezeAppealApproval`）のときは
 * アウトがしきい値未満なら `is_permanently_banned` を解除する。
 */
class UserOutCountFreezeService
{
    public function processOutCountAndFreeze(User $user, bool $afterFreezeAppealApproval = false): void
    {
        Report::resetExpiredOutCounts();
        $user->refresh();

        $currentOut = $user->calculateOutCount();
        $snapshot = $user->sanctions_out_count_snapshot;
        $sanctionsOutIncreased = $snapshot === null || $this->outStrictlyGreaterThan($currentOut, (float) $snapshot);

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

            $this->persistSanctionsOutCountSnapshot($user);

            return;
        }

        if ($afterFreezeAppealApproval) {
            $liftPermanentBan = $user->is_permanently_banned && !$user->shouldBePermanentlyBanned();
            if ($liftPermanentBan) {
                $user->is_permanently_banned = false;
            }

            $hadFutureTempFreeze = $user->frozen_until && $user->frozen_until->isFuture();
            if ($hadFutureTempFreeze) {
                $user->logFreeze(null, '異議申し立て承認により一時凍結を終了');
            }

            $user->frozen_until = null;
            $user->freeze_period_started_at = null;
            $nextFreezeCount = max(0, (int) $user->freeze_count - 1);
            $user->freeze_count = $currentOut < 1.0 ? 0 : $nextFreezeCount;
            $user->save();

            if ($liftPermanentBan) {
                $user->refresh();
                $user->logPermanentBanLift('異議申し立て承認によりアウト数が永久凍結しきい値未満となったため解除');
            }

            $this->persistSanctionsOutCountSnapshot($user);

            return;
        }

        if ($user->shouldBeTemporarilyFrozen() && $sanctionsOutIncreased) {
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
            if ($sanctionsOutIncreased && $currentOut >= 1.0 && $currentOut < 2.0) {
                $suppressMonths = max(1, (int) config('report_restrictions.out_warning_suppress_months', 1));
                $recentWarning = AdminMessage::where('user_id', $user->user_id)
                    ->where('title', '利用に関する警告')
                    ->where('created_at', '>=', now()->subMonths($suppressMonths))
                    ->exists();

                if (!$recentWarning) {
                    $this->sendWarningNotice($user);
                }
            }

            if ($currentOut < 1.0 && $user->frozen_until) {
                $user->freeze_count = 0;
                $user->frozen_until = null;
                $user->freeze_period_started_at = null;
                $user->save();
                $user->logFreeze(null, 'アウト数が0になったため凍結解除');
            }
        }

        $this->persistSanctionsOutCountSnapshot($user);
    }

    /**
     * 制裁ロジック用: 現在のアウト合計を保存（次回呼び出しで増減判定に使う）
     */
    private function persistSanctionsOutCountSnapshot(User $user): void
    {
        $user->refresh();
        $user->sanctions_out_count_snapshot = $user->calculateOutCount();
        $user->save();
    }

    private function outStrictlyGreaterThan(float $a, float $b): bool
    {
        return round($a, 4) > round($b, 4);
    }

    private function sendWarningNotice(User $user): void
    {
        $isEn = strtoupper((string) ($user->language ?? 'JA')) === 'EN';
        $body = $isEn
            ? "A violation has been detected in your post.\nIf such behavior continues, your account may be suspended.\nPlease ensure that your future posts comply with our terms of service."
            : "あなたの投稿について、違反行為が確認されました。\n今後、同様の行為を続けると、アカウントが凍結される可能性があります。利用規約を遵守した投稿をお願いいたします。";

        AdminMessage::create([
            'title' => $isEn ? 'Warning Notice' : '利用に関する警告',
            'body' => $body,
            'audience' => 'members',
            'user_id' => $user->user_id,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
    }

    private function sendFreezeNotice(User $user, \Carbon\Carbon $freezeUntil): void
    {
        $isEn = strtoupper((string) ($user->language ?? 'JA')) === 'EN';
        $freezeUntilFormatted = $isEn ? $freezeUntil->format('F d, Y H:i') : $freezeUntil->format('Y年m月d日 H:i');
        $body = $isEn
            ? "Your account has been temporarily suspended.\n\nSuspension ends at: {$freezeUntilFormatted}\n\nDuring the suspension period, you will not be able to perform any actions other than browsing.\nPlease ensure future use complies with our terms of service."
            : "あなたのアカウントが一時的に凍結されました。\n\n凍結解除予定日時: {$freezeUntilFormatted}\n\n凍結期間中は、閲覧以外の操作はできません。\n今後は利用規約を遵守した投稿をお願いいたします。";

        AdminMessage::create([
            'title' => $isEn ? 'Temporary Account Suspension' : 'アカウント一時凍結のお知らせ',
            'body' => $body,
            'audience' => 'members',
            'user_id' => $user->user_id,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
    }

    private function sendPermanentBanNotice(User $user): void
    {
        $isEn = strtoupper((string) ($user->language ?? 'JA')) === 'EN';
        $body = $isEn
            ? "Your account has been permanently suspended.\nYou can still log in to this account, but you will not be able to perform any actions other than browsing.\nYou also cannot register a new account using the same email address or phone number."
            : "あなたのアカウントが永久凍結されました。\n今後、このアカウントでログインすることはできますが、閲覧以外の操作はできません。また、同じメールアドレスおよび電話番号での新規登録もできません。";

        AdminMessage::create([
            'title' => $isEn ? 'Permanent Account Suspension' : 'アカウント永久凍結のお知らせ',
            'body' => $body,
            'audience' => 'members',
            'user_id' => $user->user_id,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
        ]);
    }
}
