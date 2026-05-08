<?php

namespace App\Policies;

use App\Models\FriendRequest;
use App\Models\User;

class FriendRequestPolicy
{
    /**
     * ユーザーがフレンド申請を送信できるかどうか
     */
    public function send(User $user, User $targetUser): bool
    {
        // 自分自身には申請できない
        if ($user->user_id === $targetUser->user_id) {
            return false;
        }

        if (!empty($user->is_admin) || !empty($targetUser->is_admin)) {
            return false;
        }
        
        // ログインユーザーは他のユーザーに申請可能
        return true;
    }

    /**
     * ユーザーがフレンド申請を承認できるかどうか
     */
    public function accept(User $user, FriendRequest $friendRequest): bool
    {
        if ($user->user_id !== $friendRequest->to_user_id) {
            return false;
        }

        $fromUser = User::query()->find($friendRequest->from_user_id);
        $toUser = User::query()->find($friendRequest->to_user_id);
        if (($fromUser && !empty($fromUser->is_admin)) || ($toUser && !empty($toUser->is_admin))) {
            return false;
        }

        return true;
    }

    /**
     * ユーザーがフレンド申請を拒否できるかどうか
     */
    public function reject(User $user, FriendRequest $friendRequest): bool
    {
        // 申請の受信者のみ拒否可能
        return $user->user_id === $friendRequest->to_user_id;
    }

    /**
     * ユーザーがフレンドにコインを送信できるかどうか
     */
    public function sendCoins(User $user, User $friend): bool
    {
        if ($user->user_id === $friend->user_id) {
            return false;
        }

        if (!empty($user->is_admin) || !empty($friend->is_admin)) {
            return false;
        }

        return true;
    }

    /**
     * ユーザーがフレンドを削除できるかどうか
     */
    public function deleteFriend(User $user, User $friend): bool
    {
        // 自分自身は削除できない
        if ($user->user_id === $friend->user_id) {
            return false;
        }

        if (!empty($user->is_admin)) {
            return false;
        }
        
        return true;
    }
}
