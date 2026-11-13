<?php

namespace App\Services;

use App\Models\Report;
use App\Models\Response;
use App\Models\Thread;
use Illuminate\Support\Collection;

/**
 * 通報者の重みは保存しない。通報の追加・更新・承認・拒否のときだけ、
 * その人の直近6か月の通報から計算する。
 * しきい値に達した表示制限と、承認による削除扱いは対象行のフラグで持つ。
 * ページ表示はフラグを読むだけ。フラグは一度立ったら戻さない。
 */
class ReportTargetStateService
{
    public const RESTRICTED_REASONS = [
        'スパム・迷惑行為',
        '攻撃的・不適切な内容',
        '不適切なリンク・外部誘導',
        '成人向け以外のコンテンツ規制違反',
        'その他',
    ];

    public const IDEOLOGY_REASON = '異なる思想に関しての意見の押し付け、妨害';

    public const ADULT_REASON = '成人向けコンテンツが含まれる';

    public static function syncReportTarget(Report $report): void
    {
        if ($report->response_id) {
            self::syncResponse((int) $report->response_id);
            return;
        }
        if ($report->thread_id) {
            self::syncThread((int) $report->thread_id);
        }
    }

    public static function syncThread(int $threadId): void
    {
        $thread = Thread::withTrashed()->find($threadId);
        if (!$thread) {
            return;
        }
        $reports = Report::query()
            ->where('thread_id', $threadId)
            ->whereNull('response_id')
            ->get(['report_id', 'user_id', 'reason', 'is_approved', 'approved_at']);
        self::applyFlags($thread, $reports);
    }

    public static function syncResponse(int $responseId): void
    {
        $response = Response::find($responseId);
        if (!$response) {
            return;
        }
        $reports = Report::query()
            ->where('response_id', $responseId)
            ->get(['report_id', 'user_id', 'reason', 'is_approved', 'approved_at']);
        self::applyFlags($response, $reports);
    }

    /**
     * @param  Collection<int, Report>  $reports
     */
    private static function applyFlags(Thread|Response $target, Collection $reports): void
    {
        $changed = false;

        $hasApproved = $reports->contains(fn (Report $report) => $report->is_approved === true && $report->approved_at !== null);
        if ($hasApproved && !$target->deleted_by_report) {
            $target->deleted_by_report = true;
            $changed = true;
        }

        $counting = $reports->filter(fn (Report $report) => $report->is_approved === true || $report->approved_at === null);
        $evaluation = self::evaluate($counting);

        if ($evaluation['restricted'] && !$target->report_restricted) {
            $target->report_restricted = true;
            $changed = true;
        }
        $reasons = $target->report_restricted ? $evaluation['reasons'] : [];
        $current = is_array($target->report_restriction_reasons) ? $target->report_restriction_reasons : [];
        if ($reasons !== array_values($current)) {
            $target->report_restriction_reasons = $reasons;
            $changed = true;
        }

        if ($changed) {
            $target->save();
        }
    }

    /**
     * @param  Collection<int, Report>  $reports
     * @return array{restricted: bool, reasons: list<string>}
     */
    public static function evaluate(Collection $reports): array
    {
        $restrictedScore = 0.0;
        $ideologyScore = 0.0;
        $adultScore = 0.0;
        $reasons = [];

        $weights = self::currentWeights($reports->pluck('user_id')->all());

        foreach ($reports as $report) {
            $weight = $weights[(int) $report->user_id] ?? 0.30;
            if (in_array($report->reason, self::RESTRICTED_REASONS, true)) {
                $restrictedScore += $weight;
                if (!in_array($report->reason, $reasons, true)) {
                    $reasons[] = $report->reason;
                }
            } elseif ($report->reason === self::IDEOLOGY_REASON) {
                $ideologyScore += $weight;
            } elseif ($report->reason === self::ADULT_REASON) {
                $adultScore += $weight;
            }
        }

        if ($ideologyScore >= 3.0 && !in_array(self::IDEOLOGY_REASON, $reasons, true)) {
            $reasons[] = self::IDEOLOGY_REASON;
        }
        if ($adultScore >= 2.0 && !in_array(self::ADULT_REASON, $reasons, true)) {
            $reasons[] = self::ADULT_REASON;
        }

        return [
            'restricted' => $restrictedScore >= 1.0 || $ideologyScore >= 3.0 || $adultScore >= 2.0,
            'reasons' => array_values($reasons),
        ];
    }

    /**
     * 通報者ごとの今の重み。直近6か月の承認件数 ÷ 通報件数。5件以下は 0.30。範囲は 0.3〜0.8。
     *
     * @param  list<int|string|null>  $userIds
     * @return array<int, float>
     */
    private static function currentWeights(array $userIds): array
    {
        $userIds = array_values(array_unique(array_map('intval', array_filter($userIds, fn ($id) => $id !== null && $id !== ''))));
        $weights = [];
        foreach ($userIds as $userId) {
            $weights[$userId] = 0.30;
        }
        if ($userIds === []) {
            return $weights;
        }

        $rows = Report::query()
            ->whereIn('user_id', $userIds)
            ->where('created_at', '>=', now()->subMonths(6))
            ->get(['user_id', 'is_approved']);

        $totals = [];
        $approved = [];
        foreach ($rows as $row) {
            $userId = (int) $row->user_id;
            $totals[$userId] = ($totals[$userId] ?? 0) + 1;
            if ($row->is_approved === true) {
                $approved[$userId] = ($approved[$userId] ?? 0) + 1;
            }
        }

        foreach ($totals as $userId => $total) {
            if ($total <= 5) {
                continue;
            }
            $weights[$userId] = round(max(0.3, min(0.8, ($approved[$userId] ?? 0) / $total)), 2);
        }

        return $weights;
    }

    public static function backfill(): void
    {
        $threadIds = Report::query()->whereNotNull('thread_id')->whereNull('response_id')->distinct()->pluck('thread_id');
        foreach ($threadIds as $threadId) {
            self::syncThread((int) $threadId);
        }

        $responseIds = Report::query()->whereNotNull('response_id')->distinct()->pluck('response_id');
        foreach ($responseIds as $responseId) {
            self::syncResponse((int) $responseId);
        }
    }
}
