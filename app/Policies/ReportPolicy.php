<?php

namespace App\Policies;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Auth;

class ReportPolicy
{
    /**
     * ユーザーが通報を作成できるかどうか
     */
    public function create(User $user): bool
    {
        // ログインユーザーは通報可能
        return true;
    }

    /**
     * ユーザーが自分の通報を表示できるかどうか
     */
    public function view(User $user, Report $report): bool
    {
        // 自分の通報のみ表示可能
        return $user->user_id === $report->user_id;
    }
}
