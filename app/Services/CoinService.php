<?php

namespace App\Services;

use App\Models\User;
use App\Models\AdWatchHistory;
use App\Models\ConsecutiveLoginReward;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CoinService
{
    /**
     * http(s) URL 検出（SafeBrowsingService::extractUrls と同じパターン）
     */
    private const HTTP_URL_REGEX = '/https?:\/\/[^\s<>"{}|\\^`\[\]]+/i';

    /**
     * コインを消費する
     */
    public function consumeCoins(User $user, int $amount): bool
    {
        if (!empty($user->is_admin)) {
            return true;
        }

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
        if (!empty($user->is_admin)) {
            return;
        }

        $user->increment('coins', $amount);
    }

    /**
     * スレッド作成に必要なコイン（ルーム分のみ）
     */
    public function getThreadCreationCost(): int
    {
        return 2;
    }

    /**
     * ルーム作成時の1リプライ目（本文）コイン（1〜100文字で1コイン、以降100文字ごとに切り上げ）
     * 改行・空白も1文字として数える（trim しない）。URLは出現1件につき課金上1文字。
     */
    public function getThreadBodyCoinCost(string $body): int
    {
        if ($body === '') {
            return 0;
        }
        $urlCount = (int) preg_match_all(self::HTTP_URL_REGEX, $body);
        $textOnly = preg_replace(self::HTTP_URL_REGEX, '', $body);
        $charCount = mb_strlen($textOnly) + $urlCount;

        return $charCount > 0 ? (int) ceil($charCount / 100) : 0;
    }

    /**
     * レスポンス送信に必要なコインを計算
     * メディア添付ごとに1コイン。本文は URL 以外を文字どおり数え、URL は出現1件につき課金上1文字。
     * 合計を1〜100で1コイン、以降100文字ごとに切り上げ。改行・空白も1文字（trim しない）。
     */
    public function getResponseCost(string $body, bool $hasMediaFile): int
    {
        $urlCount = (int) preg_match_all(self::HTTP_URL_REGEX, $body);
        $textOnly = preg_replace(self::HTTP_URL_REGEX, '', $body);
        $charCount = mb_strlen($textOnly) + $urlCount;

        $mediaCost = $hasMediaFile ? 1 : 0;
        $textCost = $charCount > 0 ? (int) ceil($charCount / 100) : 0;

        return $mediaCost + $textCost;
    }

    /**
     * 広告動画視聴によるコイン配布（3～5コイン、ルーレット）
     */
    public function rewardAdWatch(User $user): array
    {
        if (!empty($user->is_admin)) {
            $lang = LanguageService::getCurrentLanguage();

            return [
                'success' => false,
                'message' => LanguageService::trans('admin_no_coin_reward_needed', $lang),
            ];
        }

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
     * 日付・0:00境界は居住地のタイムゾーン基準。同一居住地ローカル日内の重複と、
     * reward_local_date / 記録時刻による識別で居住地変更直後の取りこぼしも抑止する。
     */
    public function rewardConsecutiveLogin(User $user): array
    {
        if (!empty($user->is_admin)) {
            $lang = LanguageService::getCurrentLanguage();

            return [
                'success' => false,
                'message' => LanguageService::trans('admin_no_coin_reward_needed', $lang),
            ];
        }

        return DB::transaction(function () use ($user) {
            /** @var User $locked */
            $locked = User::where('user_id', $user->user_id)->lockForUpdate()->firstOrFail();

            $tz = ResidenceTimezoneService::timezoneForResidence($locked->residence);
            $nowTz = Carbon::now($tz);
            $todayLocal = $nowTz->toDateString();
            $yesterdayLocal = $nowTz->copy()->subDay()->toDateString();

            $dayStartUtc = $nowTz->copy()->startOfDay()->utc();
            $nextDayStartUtc = $nowTz->copy()->addDay()->startOfDay()->utc();

            // この居住地での「今日」に既に報酬があるか（UTC の当日 0:00〜翌 0:00 に記録があるか）
            $alreadyToday = ConsecutiveLoginReward::where('user_id', $locked->user_id)
                ->where(function ($q) use ($dayStartUtc, $nextDayStartUtc, $todayLocal) {
                    $q->where(function ($q2) use ($dayStartUtc, $nextDayStartUtc) {
                        $q2->where('created_at', '>=', $dayStartUtc)
                            ->where('created_at', '<', $nextDayStartUtc);
                    })
                        ->orWhere(function ($q3) use ($todayLocal) {
                            $q3->whereNotNull('reward_local_date')
                                ->where('reward_local_date', $todayLocal);
                        });
                })
                ->exists();

            if ($alreadyToday) {
                return [
                    'success' => false,
                    'message' => '今日は既に報酬を受け取りました',
                ];
            }

            $prevReward = ConsecutiveLoginReward::where('user_id', $locked->user_id)
                ->orderByDesc('created_at')
                ->first();

            if ($prevReward) {
                $prevLocalDate = Carbon::parse($prevReward->created_at)->timezone($tz)->toDateString();

                if ($prevLocalDate === $todayLocal) {
                    return [
                        'success' => false,
                        'message' => '今日は既に報酬を受け取りました',
                    ];
                }

                if ($prevLocalDate === $yesterdayLocal) {
                    $locked->increment('consecutive_login_days');
                    $locked->refresh();
                } else {
                    $locked->consecutive_login_days = 1;
                    $locked->save();
                }
            } else {
                // 既存ユーザーのみ reward 行が無い場合は last_login_date で連続を推定
                $lastLoginDate = $locked->last_login_date;
                if ($lastLoginDate && $lastLoginDate === $yesterdayLocal) {
                    $locked->increment('consecutive_login_days');
                    $locked->refresh();
                } elseif (!$lastLoginDate || $lastLoginDate !== $todayLocal) {
                    $locked->consecutive_login_days = 1;
                    $locked->save();
                } else {
                    return [
                        'success' => false,
                        'message' => '今日は既に報酬を受け取りました',
                    ];
                }
            }

            $consecutiveDays = (int) $locked->consecutive_login_days;
            $coins = $this->calculateConsecutiveLoginReward($consecutiveDays);

            $this->addCoins($locked, $coins);

            ConsecutiveLoginReward::create([
                'user_id' => $locked->user_id,
                'reward_date' => $todayLocal,
                'reward_local_date' => $todayLocal,
                'residence_at_reward' => $locked->residence,
                'coins_rewarded' => $coins,
                'consecutive_days' => $consecutiveDays,
            ]);

            $locked->last_login_date = $todayLocal;
            $locked->save();

            return [
                'success' => true,
                'coins' => $coins,
                'consecutive_days' => $consecutiveDays,
            ];
        });
    }
}

