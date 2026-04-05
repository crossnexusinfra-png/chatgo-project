<?php

namespace App\Services;

use App\Models\Report;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * 承認済み通報の out_count を減算し、対象ユーザーのアウト合計を下げる。
 */
class UserOutCountReductionService
{
    /**
     * @return float 実際に減算できたアウト数
     */
    public function subtractFromUserReports(User $user, float $amount): float
    {
        if ($amount <= 0) {
            return 0.0;
        }

        $oneYearAgo = now()->subYear();
        $remaining = round($amount, 2);
        $reduced = 0.0;

        DB::transaction(function () use ($user, $oneYearAgo, &$remaining, &$reduced) {
            $reports = Report::query()
                ->where('is_approved', true)
                ->whereNotNull('approved_at')
                ->where('approved_at', '>=', $oneYearAgo)
                ->where(function ($q) use ($user) {
                    $q->whereHas('thread', function ($qq) use ($user) {
                        $qq->where('user_id', $user->user_id);
                    })
                        ->orWhereHas('response', function ($qq) use ($user) {
                            $qq->where('user_id', $user->user_id);
                        })
                        ->orWhere('reported_user_id', $user->user_id);
                })
                ->where('out_count', '>', 0)
                ->orderByDesc('approved_at')
                ->lockForUpdate()
                ->get();

            foreach ($reports as $report) {
                if ($remaining <= 0) {
                    break;
                }
                $oc = (float) ($report->out_count ?? 0);
                if ($oc <= 0) {
                    continue;
                }
                $take = round(min($oc, $remaining), 2);
                $newOc = round($oc - $take, 2);
                $report->out_count = $newOc < 0.01 ? 0.0 : $newOc;
                $report->save();
                $remaining = round($remaining - $take, 2);
                $reduced = round($reduced + $take, 2);
            }
        });

        Cache::forget('user_report_score_' . $user->user_id);

        return $reduced;
    }
}
