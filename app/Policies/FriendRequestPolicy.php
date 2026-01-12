<?php

namespace App\Policies;

use App\Models\FriendRequest;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

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
        
        // ログインユーザーは他のユーザーに申請可能
        return true;
    }

    /**
     * ユーザーがフレンド申請を承認できるかどうか
     */
    public function accept(User $user, FriendRequest $friendRequest): bool
    {
        // 申請の受信者のみ承認可能
        return $user->user_id === $friendRequest->to_user_id;
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
        // フレンド関係にあることを確認する必要がある
        // このチェックはFriendServiceで行われるため、ここでは基本的なチェックのみ
        return $user->user_id !== $friend->user_id;
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
        
        // フレンド関係にあることを確認する必要がある
        // このチェックはFriendServiceで行われるため、ここでは基本的なチェックのみ
        return true;
    }
}
