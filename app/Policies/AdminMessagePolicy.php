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
        // 個人向けメッセージの場合、送信先ユーザーのみ閲覧可能
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        
        // 会員向けメッセージの場合、ログインユーザーは閲覧可能
        if ($message->audience === 'members') {
            return true;
        }
        
        // 非会員向けメッセージの場合、非ログインユーザーは閲覧可能
        // ただし、このPolicyはログインユーザー用なので、ここではfalseを返す
        return false;
    }

    /**
     * ユーザーがメッセージを開封済みにできるかどうか
     */
    public function markAsRead(User $user, AdminMessage $message): bool
    {
        // 個人向けメッセージの場合、送信先ユーザーのみ開封可能
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        
        // 会員向けメッセージの場合、ログインユーザーは開封可能
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
        // 返信可能でない場合は不可
        if (!$message->allows_reply) {
            return false;
        }
        
        // 個人向けメッセージの場合、送信先ユーザーのみ返信可能
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        
        return false;
    }

    /**
     * ユーザーがメッセージからコインを受け取れるかどうか
     */
    public function receiveCoin(User $user, AdminMessage $message): bool
    {
        // コインが付与されていない場合は不可
        if (!$message->coin_amount || $message->coin_amount <= 0) {
            return false;
        }
        
        // 個人向けメッセージの場合、送信先ユーザーのみ受け取り可能
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        
        // 会員向けメッセージの場合、ログインユーザーは受け取り可能
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
        // R18変更リクエストのお知らせでない場合は不可
        if ($message->title_key !== 'r18_change_request_title' || !$message->thread_id) {
            return false;
        }
        
        // 個人向けメッセージの場合、送信先ユーザーのみ承認可能
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        
        return false;
    }

    /**
     * ユーザーがR18変更リクエストを拒否できるかどうか
     */
    public function rejectR18Change(User $user, AdminMessage $message): bool
    {
        // R18変更リクエストのお知らせでない場合は不可
        if ($message->title_key !== 'r18_change_request_title' || !$message->thread_id) {
            return false;
        }
        
        // 個人向けメッセージの場合、送信先ユーザーのみ拒否可能
        if ($message->user_id !== null) {
            return $message->user_id === $user->user_id;
        }
        
        return false;
    }
}
