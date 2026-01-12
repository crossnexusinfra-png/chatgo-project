<?php

namespace App\Services;

use App\Models\User;
use App\Models\AdWatchHistory;
use App\Models\ConsecutiveLoginReward;
use Illuminate\Support\Facades\DB;

class CoinService
{
    /**
     * コインを消費する
     */
    public function consumeCoins(User $user, int $amount): bool
    {
        if ($user->coins < $amount) {
            return false;
        }

        $user->decrement('coins', $amount);
        return true;
    }

    /**
     * コインを追加する
     */
    public function addCoins(User $user, int $amount): void
    {
        $user->increment('coins', $amount);
    }

    /**
     * スレッド作成に必要なコインを計算
     */
    public function getThreadCreationCost(): int
    {
        return 2;
    }

    /**
     * レスポンス送信に必要なコインを計算
     * URLを除く100文字ごとに1コイン追加
     */
    public function getResponseCost(string $body, bool $hasMediaFile, bool $hasText): int
    {
        $baseCost = 1;
        
        // メディアファイル単体の送信で1コイン必要（文字も同時に送る場合は0文字としてカウント）
        if ($hasMediaFile && !$hasText) {
            return 1;
        }
        
        // 文字も同時に送る場合、メディアファイルは0文字としてカウント（メディアファイルのコストは含まれない）
        // URLを除いた文字数を計算
        $textWithoutUrls = preg_replace('/https?:\/\/[^\s]+/', '', $body);
        $charCount = mb_strlen(trim($textWithoutUrls));
        
        // 100文字ごとに1コイン追加
        $additionalCost = floor($charCount / 100);
        
        return $baseCost + $additionalCost;
    }

    /**
     * 広告動画視聴によるコイン配布（3～5コイン、ルーレット）
     */
    public function rewardAdWatch(User $user): array
    {
        $today = now()->toDateString();
        
        // 今日の視聴回数を取得
        $watchHistory = AdWatchHistory::firstOrCreate(
            ['user_id' => $user->user_id, 'watch_date' => $today],
            ['watch_count' => 0]
        );
        
        // 1日10回まで
        if ($watchHistory->watch_count >= 10) {
            return [
                'success' => false,
                'message' => '今日の視聴回数上限に達しています',
            ];
        }
        
        // ルーレットで3～5コインを決定（確率: 5コイン70%、4コイン20%、3コイン10%）
        $rand = random_int(0, 99);
        if ($rand < 70) {
            // 0-69: 5コイン (70%)
            $coins = 5;
        } elseif ($rand < 90) {
            // 70-89: 4コイン (20%)
            $coins = 4;
        } else {
            // 90-99: 3コイン (10%)
            $coins = 3;
        }
        
        // コインを追加
        $this->addCoins($user, $coins);
        
        // 視聴回数を増やす
        $watchHistory->increment('watch_count');
        
        return [
            'success' => true,
            'coins' => $coins,
            'remaining_watches' => 10 - $watchHistory->watch_count,
        ];
    }

    /**
     * 連続ログイン報酬を計算
     */
    public function calculateConsecutiveLoginReward(int $consecutiveDays): int
    {
        if ($consecutiveDays == 1) {
            return 1;
        } elseif ($consecutiveDays == 2) {
            return 2;
        } elseif ($consecutiveDays == 3) {
            return 2;
        } elseif ($consecutiveDays == 4) {
            return 3;
        } elseif ($consecutiveDays == 5) {
            return 3;
        } elseif ($consecutiveDays == 6) {
            return 4;
        } elseif ($consecutiveDays == 7) {
            return 5;
        } elseif ($consecutiveDays % 100 == 0) {
            // 100日ごとに10コイン（50日ごとより優先）
            return 10;
        } elseif ($consecutiveDays % 50 == 0) {
            // 50日ごとに7コイン
            return 7;
        } else {
            // 8日目以降は4と5を交互に
            $daysAfter7 = $consecutiveDays - 7;
            return ($daysAfter7 % 2 == 1) ? 4 : 5;
        }
    }

    /**
     * 連続ログイン報酬を配布
     */
    public function rewardConsecutiveLogin(User $user): array
    {
        $today = now()->toDateString();
        $lastLoginDate = $user->last_login_date;
        
        // 今日既に報酬を受け取っているかチェック
        $todayReward = ConsecutiveLoginReward::where('user_id', $user->user_id)
            ->where('reward_date', $today)
            ->first();
        
        if ($todayReward) {
            return [
                'success' => false,
                'message' => '今日は既に報酬を受け取りました',
            ];
        }
        
        // 連続ログイン日数を計算
        if ($lastLoginDate) {
            $lastLogin = \Carbon\Carbon::parse($lastLoginDate);
            $yesterday = now()->subDay()->toDateString();
            
            if ($lastLogin->toDateString() == $yesterday) {
                // 連続ログイン
                $user->increment('consecutive_login_days');
            } elseif ($lastLogin->toDateString() != $today) {
                // 連続ログインが途切れた
                $user->consecutive_login_days = 1;
            }
        } else {
            // 初回ログイン
            $user->consecutive_login_days = 1;
        }
        
        $consecutiveDays = $user->consecutive_login_days;
        $coins = $this->calculateConsecutiveLoginReward($consecutiveDays);
        
        // コインを追加
        $this->addCoins($user, $coins);
        
        // 報酬記録を保存
        ConsecutiveLoginReward::create([
            'user_id' => $user->user_id,
            'reward_date' => $today,
            'coins_rewarded' => $coins,
            'consecutive_days' => $consecutiveDays,
        ]);
        
        // 最終ログイン日を更新
        $user->last_login_date = $today;
        $user->save();
        
        return [
            'success' => true,
            'coins' => $coins,
            'consecutive_days' => $consecutiveDays,
        ];
    }
}

