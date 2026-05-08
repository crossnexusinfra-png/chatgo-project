<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Services\CoinService;
use App\Services\LanguageService;

class CoinController extends Controller
{
    protected $coinService;

    public function __construct(CoinService $coinService)
    {
        $this->coinService = $coinService;
    }

    /**
     * 広告動画視聴によるコイン配布
     */
    public function watchAd(Request $request)
    {
        $user = Auth::user();
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('coins.watch-ad', $user->user_id);
        if (!$lock) {
            $lang = LanguageService::getCurrentLanguage();
            return response()->json(['success' => false, 'message' => LanguageService::trans('duplicate_submission', $lang)], 429);
        }
        try {
        $result = $this->coinService->rewardAdWatch($user);
        
        $lang = LanguageService::getCurrentLanguage();
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'coins' => $result['coins'],
                'remaining_watches' => $result['remaining_watches'],
                'message' => LanguageService::trans('ad_watch_reward', $lang, ['coins' => $result['coins']]),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }
        } finally {
            $lock->release();
        }
    }

    /**
     * 連続ログイン報酬を受け取る
     */
    public function claimLoginReward(Request $request)
    {
        $user = Auth::user();
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('coins.claim-login-reward', $user->user_id);
        if (!$lock) {
            $lang = LanguageService::getCurrentLanguage();
            return response()->json(['success' => false, 'message' => LanguageService::trans('duplicate_submission', $lang)], 429);
        }
        try {
        $result = $this->coinService->rewardConsecutiveLogin($user);
        
        $lang = LanguageService::getCurrentLanguage();
        
        if ($result['success']) {
            return response()->json([
                'success' => true,
                'coins' => $result['coins'],
                'consecutive_days' => $result['consecutive_days'],
                'message' => LanguageService::trans('login_reward_claimed', $lang, [
                    'coins' => $result['coins'],
                    'days' => $result['consecutive_days'],
                ]),
            ]);
        } else {
            return response()->json([
                'success' => false,
                'message' => $result['message'],
            ], 400);
        }
        } finally {
            $lock->release();
        }
    }

    /**
     * コイン残高を取得
     */
    public function getBalance(Request $request)
    {
        // AJAXリクエストでない場合はマイページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->route('profile.index');
        }
        
        $user = Auth::user();
        if (!empty($user->is_admin)) {
            return response()->json([
                'coins' => null,
                'unlimited' => true,
            ]);
        }

        return response()->json([
            'coins' => $user->coins,
            'unlimited' => false,
        ]);
    }
}

