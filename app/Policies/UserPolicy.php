<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class UserPolicy
{
    /**
     * ユーザーが自分のプロフィールを更新できるかどうか
     */
    public function update(User $user, User $model): bool
    {
        // 自分のプロフィールのみ更新可能
        return $user->user_id === $model->user_id;
    }

    /**
     * ユーザーが自分のプロフィールを表示できるかどうか
     */
    public function view(User $user, User $model): bool
    {
        // 自分のプロフィールのみ表示可能（マイページ）
        return $user->user_id === $model->user_id;
    }

    /**
     * ユーザーが他のユーザーのプロフィールを表示できるかどうか（公開プロフィール）
     */
    public function viewPublic(User $user, User $model): bool
    {
        // 公開プロフィールは誰でも閲覧可能
        return true;
    }

    /**
     * ユーザーが自分のスレッド一覧を取得できるかどうか
     */
    public function viewThreads(User $user, User $model): bool
    {
        // 自分のスレッド一覧のみ取得可能
        return $user->user_id === $model->user_id;
    }
}
