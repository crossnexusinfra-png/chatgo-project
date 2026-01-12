<?php

namespace App\Policies;

use App\Models\User;
use Illuminate\Support\Facades\Auth;

class AdminPolicy
{
    /**
     * ユーザーが管理者機能にアクセスできるかどうか
     */
    public function accessAdmin(User $user): bool
    {
        // 管理者認証はmiddlewareで行われるため、ここでは常にtrueを返す
        // 実際の認証チェックはAdminBasicAuthミドルウェアで行われる
        return true;
    }

    /**
     * ユーザーが通報を承認/拒否できるかどうか
     */
    public function manageReports(User $user): bool
    {
        // 管理者認証はmiddlewareで行われるため、ここでは常にtrueを返す
        return true;
    }

    /**
     * ユーザーが改善要望を管理できるかどうか
     */
    public function manageSuggestions(User $user): bool
    {
        // 管理者認証はmiddlewareで行われるため、ここでは常にtrueを返す
        return true;
    }

    /**
     * ユーザーがお知らせを管理できるかどうか
     */
    public function manageMessages(User $user): bool
    {
        // 管理者認証はmiddlewareで行われるため、ここでは常にtrueを返す
        return true;
    }
}
