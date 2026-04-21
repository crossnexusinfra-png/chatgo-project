<?php

namespace App\Services;

use App\Models\AdminMessage;
use App\Models\User;

class WelcomeNotificationService
{
    /**
     * 初回登録時お知らせテンプレートが設定されていれば、該当ユーザーに送信する
     */
    public static function sendWelcomeTo(User $user): void
    {
        $template = AdminMessage::where('is_welcome', true)
            ->whereNull('published_at')
            ->whereNull('parent_message_id')
            ->first();

        if (!$template) {
            return;
        }

        AdminMessage::create([
            'title_key' => $template->title_key,
            'body_key' => $template->body_key,
            'title' => $template->title,
            'body' => $template->body,
            'title_ja' => $template->getAttributeValue('title_ja'),
            'title_en' => $template->getAttributeValue('title_en'),
            'body_ja' => $template->getAttributeValue('body_ja'),
            'body_en' => $template->getAttributeValue('body_en'),
            'audience' => 'members',
            'user_id' => $user->user_id,
            'published_at' => now(),
            'allows_reply' => $template->allows_reply ?? false,
            'unlimited_reply' => $template->unlimited_reply ?? false,
            'reply_used' => false,
            'coin_amount' => $template->coin_amount,
            'is_auto_sent' => true,
        ]);
    }
}
