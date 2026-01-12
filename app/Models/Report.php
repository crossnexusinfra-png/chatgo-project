<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Report extends Model
{
    use HasFactory;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'report_id';

    /**
     * フォームからの入力を許可するカラムを指定します。
     *
     * @var array
     */
    protected $fillable = [
        'user_id',
        'thread_id',
        'response_id',
        'reported_user_id',
        'reason',
        'description',
        'is_approved',
        'approved_at',
        'flagged',
        'out_count',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_approved' => 'boolean',
            'approved_at' => 'datetime',
            'flagged' => 'boolean',
        ];
    }

    /**
     * この通報を作成したユーザーを取得します。
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * この通報が対象とするスレッドを取得します。
     */
    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'thread_id');
    }

    /**
     * この通報が対象とするレスポンスを取得します。
     */
    public function response()
    {
        return $this->belongsTo(Response::class, 'response_id', 'response_id');
    }

    /**
     * この通報が対象とするユーザー（プロフィール）を取得します。
     */
    public function reportedUser()
    {
        return $this->belongsTo(User::class, 'reported_user_id', 'user_id');
    }

    /**
     * ユーザーの通報スコアを計算する
     * 通報スコア = アウト成立数 ÷ 通報件数（ただし5件以下は0.30固定）
     * 最大値0.8、最小値0.3
     * キャッシュを使用してパフォーマンスを向上
     * 
     * @param int $userId
     * @return float 通報スコア（0.3〜0.8）
     */
    public static function calculateUserReportScore($userId): float
    {
        // user_idがnullの場合はデフォルト値0.3を返す
        if ($userId === null) {
            return 0.3;
        }
        
        // キャッシュキー（5分間キャッシュ）
        $cacheKey = 'user_report_score_' . $userId;
        
        return \Illuminate\Support\Facades\Cache::remember($cacheKey, 300, function () use ($userId) {
            $sixMonthsAgo = now()->subMonths(6);
            
            // 半年以内の通報を取得
            $reports = self::where('user_id', $userId)
                ->where('created_at', '>=', $sixMonthsAgo)
                ->get();
            
            $totalReports = $reports->count();
            
            // 5件通報まではスコア0.30固定
            if ($totalReports <= 5) {
                return 0.30;
            }
            
            // アウト成立数（承認された通報数）
            $approvedCount = $reports->where('is_approved', true)->count();
            
            // 通報スコア = アウト成立数 ÷ 通報件数
            $score = $totalReports > 0 ? ($approvedCount / $totalReports) : 0.0;
            
            // スコアを0.3〜0.8の範囲に制限
            $score = max(0.3, min($score, 0.8));
            
            return $score;
        });
    }

    /**
     * スレッドの通報スコアを計算する
     * 
     * @param int $threadId
     * @return float 通報スコア（0.0〜1.0）
     */
    public static function calculateThreadReportScore($threadId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        // 半年以内の通報を取得
        $reports = self::where('thread_id', $threadId)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get();
        
        $totalReports = $reports->count();
        
        // 5件通報まではスコア0.30固定
        if ($totalReports <= 5) {
            return 0.30;
        }
        
        // アウト成立数（承認された通報数）
        $approvedCount = $reports->where('is_approved', true)->count();
        
        // 通報スコア = アウト成立数 ÷ 通報件数
        $score = $totalReports > 0 ? ($approvedCount / $totalReports) : 0.0;
        
        // スコア最大値1
        return min($score, 1.0);
    }

    /**
     * レスポンスの通報スコアを計算する
     * 
     * @param int $responseId
     * @return float 通報スコア（0.0〜1.0）
     */
    public static function calculateResponseReportScore($responseId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        // 半年以内の通報を取得
        $reports = self::where('response_id', $responseId)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get();
        
        $totalReports = $reports->count();
        
        // 5件通報まではスコア0.30固定
        if ($totalReports <= 5) {
            return 0.30;
        }
        
        // アウト成立数（承認された通報数）
        $approvedCount = $reports->where('is_approved', true)->count();
        
        // 通報スコア = アウト成立数 ÷ 通報件数
        $score = $totalReports > 0 ? ($approvedCount / $totalReports) : 0.0;
        
        // スコア最大値1
        return min($score, 1.0);
    }

    /**
     * スレッドの特定理由による通報スコア合計を計算する
     * 「異なる思想に関しての意見の押し付け、妨害」以外の理由でのスコア合計
     * 承認の有無に関係なく、各通報者の通報スコアを合計する
     * 
     * @param int $threadId
     * @return float
     */
    public static function calculateThreadRestrictedReasonScore($threadId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        // 特定の理由で半年以内の通報を取得
        // 「異なる思想」「成人向けコンテンツが含まれる」「スレッド画像関連」は除外
        $restrictedReasons = [
            'スパム・迷惑行為',
            '攻撃的・不適切な内容',
            '不適切なリンク・外部誘導',
            '成人向け以外のコンテンツ規制違反',
            'その他'
        ];
        
        $reports = self::where('thread_id', $threadId)
            ->whereIn('reason', $restrictedReasons)
            ->where('created_at', '>=', $sixMonthsAgo)
            // 拒否された通報はスコア計算から除外（承認済みまたは未処理のみ）
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        // 各通報者の通報スコアを合計（承認済みまたは未処理のみ）
        $totalScore = 0.0;
        foreach ($reports as $report) {
            $userReportScore = self::calculateUserReportScore($report->user_id);
            $totalScore += $userReportScore;
        }
        
        return $totalScore;
    }

    /**
     * レスポンスの特定理由による通報スコア合計を計算する
     * 「異なる思想に関しての意見の押し付け、妨害」以外の理由でのスコア合計
     * 承認の有無に関係なく、各通報者の通報スコアを合計する
     * 
     * @param int $responseId
     * @return float
     */
    public static function calculateResponseRestrictedReasonScore($responseId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        // 特定の理由で半年以内の通報を取得
        // 「異なる思想」「成人向けコンテンツが含まれる」は除外
        $restrictedReasons = [
            'スパム・迷惑行為',
            '攻撃的・不適切な内容',
            '不適切なリンク・外部誘導',
            '成人向け以外のコンテンツ規制違反',
            'その他'
        ];
        
        $reports = self::where('response_id', $responseId)
            ->whereIn('reason', $restrictedReasons)
            ->where('created_at', '>=', $sixMonthsAgo)
            // 拒否された通報はスコア計算から除外（承認済みまたは未処理のみ）
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        // 各通報者の通報スコアを合計（承認済みまたは未処理のみ）
        $totalScore = 0.0;
        foreach ($reports as $report) {
            $userReportScore = self::calculateUserReportScore($report->user_id);
            $totalScore += $userReportScore;
        }
        
        return $totalScore;
    }

    /**
     * スレッドの「異なる思想」理由による通報スコア合計を計算する
     * 承認の有無に関係なく、各通報者の通報スコアを合計する
     * 
     * @param int $threadId
     * @return float
     */
    public static function calculateThreadIdeologyReportScore($threadId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        $reports = self::where('thread_id', $threadId)
            ->where('reason', '異なる思想に関しての意見の押し付け、妨害')
            ->where('created_at', '>=', $sixMonthsAgo)
            // 拒否された通報はスコア計算から除外（承認済みまたは未処理のみ）
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        // 各通報者の通報スコアを合計（承認済みまたは未処理のみ）
        $totalScore = 0.0;
        foreach ($reports as $report) {
            $userReportScore = self::calculateUserReportScore($report->user_id);
            $totalScore += $userReportScore;
        }
        
        return $totalScore;
    }

    /**
     * スレッドの「異なる思想」理由による通報数を計算する（後方互換性のため残す）
     * 
     * @param int $threadId
     * @return int
     */
    public static function calculateThreadIdeologyReportCount($threadId): int
    {
        // スコアが3.0以上の場合に制限を適用するため、スコアを返す
        $score = self::calculateThreadIdeologyReportScore($threadId);
        return (int)ceil($score); // 3.0以上で制限がかかる
    }

    /**
     * レスポンスの「異なる思想」理由による通報スコア合計を計算する
     * 承認の有無に関係なく、各通報者の通報スコアを合計する
     * 
     * @param int $responseId
     * @return float
     */
    public static function calculateResponseIdeologyReportScore($responseId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        $reports = self::where('response_id', $responseId)
            ->where('reason', '異なる思想に関しての意見の押し付け、妨害')
            ->where('created_at', '>=', $sixMonthsAgo)
            // 拒否された通報はスコア計算から除外（承認済みまたは未処理のみ）
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        // 各通報者の通報スコアを合計（承認済みまたは未処理のみ）
        $totalScore = 0.0;
        foreach ($reports as $report) {
            $userReportScore = self::calculateUserReportScore($report->user_id);
            $totalScore += $userReportScore;
        }
        
        return $totalScore;
    }

    /**
     * レスポンスの「異なる思想」理由による通報数を計算する（後方互換性のため残す）
     * 
     * @param int $responseId
     * @return int
     */
    public static function calculateResponseIdeologyReportCount($responseId): int
    {
        // スコアが3.0以上の場合に制限を適用するため、スコアを返す
        $score = self::calculateResponseIdeologyReportScore($responseId);
        return (int)ceil($score); // 3.0以上で制限がかかる
    }

    /**
     * スレッドの「成人向けコンテンツが含まれる」理由による通報スコア合計を計算する
     * 承認の有無に関係なく、各通報者の通報スコアを合計する
     * 
     * @param int $threadId
     * @return float
     */
    public static function calculateThreadAdultContentReportScore($threadId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        $reports = self::where('thread_id', $threadId)
            ->where('reason', '成人向けコンテンツが含まれる')
            ->where('created_at', '>=', $sixMonthsAgo)
            // 拒否された通報はスコア計算から除外（承認済みまたは未処理のみ）
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        // 各通報者の通報スコアを合計（承認済みまたは未処理のみ）
        $totalScore = 0.0;
        foreach ($reports as $report) {
            // user_idがnullの場合はデフォルト値0.3を使用
            if ($report->user_id === null) {
                $totalScore += 0.3;
            } else {
                $userReportScore = self::calculateUserReportScore($report->user_id);
                $totalScore += $userReportScore;
            }
        }
        
        return $totalScore;
    }

    /**
     * レスポンスの「成人向けコンテンツが含まれる」理由による通報スコア合計を計算する
     * 承認の有無に関係なく、各通報者の通報スコアを合計する
     * 
     * @param int $responseId
     * @return float
     */
    public static function calculateResponseAdultContentReportScore($responseId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        $reports = self::where('response_id', $responseId)
            ->where('reason', '成人向けコンテンツが含まれる')
            ->where('created_at', '>=', $sixMonthsAgo)
            // 拒否された通報はスコア計算から除外（承認済みまたは未処理のみ）
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        // 各通報者の通報スコアを合計（承認済みまたは未処理のみ）
        $totalScore = 0.0;
        foreach ($reports as $report) {
            // user_idがnullの場合はデフォルト値0.3を使用
            if ($report->user_id === null) {
                $totalScore += 0.3;
            } else {
                $userReportScore = self::calculateUserReportScore($report->user_id);
                $totalScore += $userReportScore;
            }
        }
        
        return $totalScore;
    }

    /**
     * スレッド画像関連の通報スコア合計を計算する
     * スレッド画像が不適切、スレッド画像が著作権に違反している、スレッド画像に個人情報・他人の写真が含まれている
     * 承認の有無に関係なく、各通報者の通報スコアを合計する
     * 
     * @param int $threadId
     * @return float
     */
    public static function calculateThreadImageReportScore($threadId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        // スレッド画像関連の理由で半年以内の通報を取得
        $threadImageReasons = [
            'スレッド画像が第三者の著作権を侵害している可能性がある',
            'スレッド画像に個人情報・他人の情報が含まれている',
            'スレッド画像に不適切な内容が含まれている'
        ];
        
        $reports = self::where('thread_id', $threadId)
            ->whereIn('reason', $threadImageReasons)
            ->where('created_at', '>=', $sixMonthsAgo)
            // 拒否された通報はスコア計算から除外（承認済みまたは未処理のみ）
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        // 各通報者の通報スコアを合計（承認済みまたは未処理のみ）
        $totalScore = 0.0;
        foreach ($reports as $report) {
            $userReportScore = self::calculateUserReportScore($report->user_id);
            $totalScore += $userReportScore;
        }
        
        return $totalScore;
    }

    /**
     * スレッド画像関連の通報理由で削除されたかどうかを判定
     * 
     * @param int $threadId
     * @return bool
     */
    public static function isThreadDeletedByImageReport($threadId): bool
    {
        // スレッド画像関連の理由
        $threadImageReasons = [
            'スレッド画像が第三者の著作権を侵害している可能性がある',
            'スレッド画像に個人情報・他人の情報が含まれている',
            'スレッド画像に不適切な内容が含まれている'
        ];
        
        return self::where('thread_id', $threadId)
            ->whereIn('reason', $threadImageReasons)
            ->where('is_approved', true)
            ->exists();
    }

    /**
     * プロフィールの特定理由による通報スコア合計を計算する
     * 承認の有無に関係なく、各通報者の通報スコアを合計する
     * 
     * @param int $userId
     * @return float
     */
    public static function calculateUserProfileRestrictedReasonScore($userId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        // プロフィール通報の理由で半年以内の通報を取得
        $restrictedReasons = [
            'スパム・迷惑行為',
            '攻撃的・不適切な内容',
            '不適切なリンク・外部誘導',
            'なりすまし・虚偽の人物情報',
            'その他'
        ];
        
        $reports = self::where('reported_user_id', $userId)
            ->whereIn('reason', $restrictedReasons)
            ->where('created_at', '>=', $sixMonthsAgo)
            // 拒否された通報はスコア計算から除外（承認済みまたは未処理のみ）
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        // 各通報者の通報スコアを合計（承認済みまたは未処理のみ）
        $totalScore = 0.0;
        foreach ($reports as $report) {
            $userReportScore = self::calculateUserReportScore($report->user_id);
            $totalScore += $userReportScore;
        }
        
        return $totalScore;
    }

    /**
     * プロフィールの通報スコアを計算する
     * 
     * @param int $userId
     * @return float 通報スコア（0.0〜1.0）
     */
    public static function calculateUserProfileReportScore($userId): float
    {
        $sixMonthsAgo = now()->subMonths(6);
        
        // 半年以内の通報を取得
        $reports = self::where('reported_user_id', $userId)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get();
        
        $totalReports = $reports->count();
        
        // 5件通報まではスコア0.30固定
        if ($totalReports <= 5) {
            return 0.30;
        }
        
        // アウト成立数（承認された通報数）
        $approvedCount = $reports->where('is_approved', true)->count();
        
        // 通報スコア = アウト成立数 ÷ 通報件数
        $score = $totalReports > 0 ? ($approvedCount / $totalReports) : 0.0;
        
        // スコア最大値1
        return min($score, 1.0);
    }

    /**
     * 1年経過した承認済み通報のアウト数をリセット
     * 通報承認から1年後にその通報分はリセット
     * 
     * @return int リセットされた通報数
     */
    public static function resetExpiredOutCounts(): int
    {
        $oneYearAgo = now()->subYear();
        
        // 1年経過した承認済み通報のアウト数を0にリセット
        $count = self::where('is_approved', true)
            ->whereNotNull('approved_at')
            ->where('approved_at', '<=', $oneYearAgo)
            ->whereNotNull('out_count')
            ->where('out_count', '>', 0)
            ->update(['out_count' => 0]);
        
        return $count;
    }

    /**
     * 通報理由に基づくデフォルトアウト数を取得
     * 
     * @param string $reason
     * @return float
     */
    public static function getDefaultOutCount(string $reason): float
    {
        // R18誤投稿、成人向け以外の規制違反、思想の押し付け：原則0.5アウト
        $halfOutReasons = [
            '成人向けコンテンツが含まれる',
            '成人向け以外のコンテンツ規制違反',
            '異なる思想に関しての意見の押し付け、妨害'
        ];
        
        if (in_array($reason, $halfOutReasons)) {
            return 0.5;
        }
        
        // その他：原則1アウト
        return 1.0;
    }
}

