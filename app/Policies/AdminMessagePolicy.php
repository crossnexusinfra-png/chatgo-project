<?php

namespace App\Policies;

use App\Models\AdminMessage;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminMessagePolicy
{
    /**
     * ユーザーがメッセージを閲覧できるかどうか
     */
    public function view(User $user, AdminMessage $message): bool
    {
        // 個人向け（1名）の場合
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        // 特定複数人向け（recipients）の場合
        if ($message->recipients()->where('users.user_id', $user->user_id)->exists()) {
            return true;
        }
        // 会員向け一斉送信（条件一致は NotificationsController で判定済み）
        if ($message->audience === 'members') {
            return true;
        }
        return false;
    }

    /**
     * ユーザーがメッセージを開封済みにできるかどうか
     */
    public function markAsRead(User $user, AdminMessage $message): bool
    {
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        if ($message->recipients()->where('users.user_id', $user->user_id)->exists()) {
            return true;
        }
        if ($message->audience === 'members') {
            return true;
        }
        return false;
    }

    /**
     * ユーザーがメッセージに返信できるかどうか
     */
    public function reply(User $user, AdminMessage $message): bool
    {
        if (!$message->allows_reply) {
            return false;
        }
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        if ($message->recipients()->where('users.user_id', $user->user_id)->exists()) {
            return true;
        }
        return false;
    }

    /**
     * ユーザーがメッセージからコインを受け取れるかどうか
     */
    public function receiveCoin(User $user, AdminMessage $message): bool
    {
        if (!$message->coin_amount || $message->coin_amount <= 0) {
            return false;
        }
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        if ($message->recipients()->where('users.user_id', $user->user_id)->exists()) {
            return true;
        }
        if ($message->audience === 'members') {
            return true;
        }
        return false;
    }

    /**
     * ユーザーがR18変更リクエストを承認できるかどうか
     */
    public function approveR18Change(User $user, AdminMessage $message): bool
    {
        if ($message->title_key !== 'r18_change_request_title' || !$message->thread_id) {
            return false;
        }
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        if ($message->recipients()->where('users.user_id', $user->user_id)->exists()) {
            return true;
        }
        return false;
    }

    /**
     * ユーザーがR18変更リクエストを拒否できるかどうか
     */
    public function rejectR18Change(User $user, AdminMessage $message): bool
    {
        if ($message->title_key !== 'r18_change_request_title' || !$message->thread_id) {
            return false;
        }
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        if ($message->recipients()->where('users.user_id', $user->user_id)->exists()) {
            return true;
        }
        return false;
    }
}
