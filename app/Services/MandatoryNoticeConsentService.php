<?php

namespace App\Services;

use App\Models\AdminMessage;
use App\Models\User;
use Illuminate\Support\Facades\Schema;

class MandatoryNoticeConsentService
{
    public static function userHasPendingRequiredConsent(User $user): bool
    {
        if (!Schema::hasColumn('admin_messages', 'requires_consent')) {
            return false;
        }
        if (!Schema::hasColumn('admin_message_reads', 'consented_at')) {
            return false;
        }

        return AdminMessage::query()
            ->publishedRootForNotifications()
            ->visibleToRecipientUser($user)
            ->where('requires_consent', true)
            ->whereDoesntHave('reads', function ($q) use ($user) {
                $q->where('user_id', $user->user_id)
                    ->whereNotNull('consented_at');
            })
            ->exists();
    }
}
