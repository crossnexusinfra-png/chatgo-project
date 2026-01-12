<?php

namespace App\Listeners;

use Illuminate\Auth\Events\Login;
use App\Models\AccessLog;

class LogSuccessfulLogin
{
    public function handle(Login $event): void
    {
        try {
            $userId = $event->user->user_id ?? $event->user->id ?? null;
            
            AccessLog::create([
                'type' => 'login',
                'user_id' => $userId,
                'path' => 'login',
                'ip' => request()->ip(),
            ]);
            
            // 連続ログイン報酬の処理（ユーザーが存在する場合のみ、自動配布＆メッセージ表示）
            if ($userId) {
                $user = \App\Models\User::find($userId);
                if ($user) {
                    $coinService = new \App\Services\CoinService();
                    $result = $coinService->rewardConsecutiveLogin($user);

                    if (($result['success'] ?? false) && isset($result['coins'], $result['consecutive_days'])) {
                        $lang = \App\Services\LanguageService::getCurrentLanguage();
                        $message = \App\Services\LanguageService::trans('login_reward_claimed', $lang, [
                            'coins' => $result['coins'],
                            'days' => $result['consecutive_days'],
                        ]);
                        session()->flash('login_reward_message', $message);
                    }
                }
            }
        } catch (\Throwable $e) {
            // 失敗は無視
        }
    }
}


