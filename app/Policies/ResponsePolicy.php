<?php

namespace App\Policies;

use App\Models\Response;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ResponsePolicy
{
    /**
     * ユーザーがレスポンスを作成できるかどうか
     */
    public function create(User $user): bool
    {
        // ログインユーザーはレスポンスを作成可能
        return true;
    }

    /**
     * ユーザーがレスポンスを更新できるかどうか
     */
    public function update(User $user, Response $response): bool
    {
        // レスポンス主のみ更新可能（現在は編集機能なし）
        return $user->user_id === $response->user_id;
    }

    /**
     * ユーザーがレスポンスを削除できるかどうか
     */
    public function delete(User $user, Response $response): bool
    {
        // レスポンス主のみ削除可能
        return $user->user_id === $response->user_id;
    }
}
