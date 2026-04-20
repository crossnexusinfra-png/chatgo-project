<?php

use Illuminate\Database\Migrations\Migration;
use App\Models\User;

return new class extends Migration
{
    /**
     * メール登録完了時に sms_verified_at が欠落していたユーザーを補正する。
     */
    public function up(): void
    {
        User::query()
            ->whereNotNull('phone')
            ->where('phone', '!=', '')
            ->whereNull('sms_verified_at')
            ->orderBy('user_id')
            ->chunkById(500, function ($users) {
                foreach ($users as $user) {
                    $user->sms_verified_at = $user->email_verified_at ?? $user->created_at ?? now();
                    $user->saveQuietly();
                }
            }, 'user_id');
    }

    public function down(): void
    {
    }
};
