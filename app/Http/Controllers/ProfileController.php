<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Carbon\Carbon;
use App\Models\User;
use App\Services\ResidenceTimezoneService;
use App\Models\ResidenceHistory;
use App\Models\AccessLog;
use App\Services\VeriphoneService;
use App\Services\ProfilePendingContactService;

class ProfileController extends Controller
{
    /**
     * 国コードから国名を取得
     */
    private function getCountryName($code)
    {
        $countries = [
            'JP' => '日本',
            'US' => 'アメリカ',
            'GB' => 'イギリス',
            'CA' => 'カナダ',
            'AU' => 'オーストラリア',
            'OTHER' => 'その他',
        ];

        return $countries[$code] ?? $code;
    }

    /**
     * マイページを表示
     */
    public function index()
    {
        // 実行時間制限を一時的に延長（パフォーマンス問題の回避）
        set_time_limit(60);
        
        // 言語を最初に取得（セッションキャッシュを活用）
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $hasExistingPhone = !empty($user->phone);
        $phoneRule = $hasExistingPhone
            ? 'required|string|max:20|unique:users,phone,' . $user->user_id . ',user_id'
            : 'nullable|string|max:20|unique:users,phone,' . $user->user_id . ',user_id';
        $newPhone = trim((string) $request->input('phone', ''));
        $newPhone = $newPhone === '' ? null : $newPhone;
        
        $user = Auth::user();
        
        // IDOR防止: 自分のプロフィールのみ表示可能
        Gate::authorize('view', $user);
        
        // 国コードを日本語名に変換
        $user->residence_display = $this->getCountryName($user->residence);
        $user->nationality_display = $this->getCountryName($user->nationality);
        
        // お気に入りルームを取得（お気に入り登録日の新しい順、最大5件）
        $favoriteThreadIds = \App\Models\ThreadFavorite::where('user_id', $user->user_id)
            ->orderByDesc('created_at')
            ->take(5)
            ->pluck('thread_id')
            ->toArray();
        $favoriteThreads = collect();
        if (!empty($favoriteThreadIds)) {
            $favoriteThreads = \App\Models\Thread::whereIn('thread_id', $favoriteThreadIds)->get();
            $favoriteThreads = $favoriteThreads->sortBy(function ($t) use ($favoriteThreadIds) {
                $pos = array_search($t->thread_id, $favoriteThreadIds);
                return $pos === false ? 999 : $pos;
            })->values();
            \App\Services\TranslationService::applyTranslatedThreadTitlesToCollection($favoriteThreads, $lang);
        }

        // ユーザーが作成したスレッドを取得（新しい順、最初の5件のみ）
        $threadsQuery = \App\Models\Thread::where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc');
        
        $totalCount = $threadsQuery->count();
        $threads = $threadsQuery->take(5)->get();
        \App\Services\TranslationService::applyTranslatedThreadTitlesToCollection($threads, $lang);
        
        // スレッドの制限情報と画像通報スコアを取得（お気に入り＋作成の両方）
        $threadRestrictionData = [];
        $threadImageReportScoreData = [];
        $allThreads = $threads->merge($favoriteThreads)->unique('thread_id');
        if ($allThreads->isNotEmpty()) {
            $threadController = new \App\Http\Controllers\ThreadController();
            $threadRestrictionData = $threadController->getThreadRestrictionData($allThreads);
            foreach ($allThreads->pluck('thread_id') as $threadId) {
                $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
                $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
                $threadImageReportScoreData[$threadId] = [
                    'score' => $score,
                    'isBlurred' => $score >= 1.0,
                    'isDeletedByImageReport' => $isDeletedByImageReport
                ];
            }
        }

        // フレンド機能の有効条件を満たした時に自動的にフレンド申請可能にする
        $friendService = new \App\Services\FriendService();
        $friendService->checkAndAutoCreateFriendRequests($user);

        // 前回のログイン日時を取得（現在のログインを除く）
        $previousLogin = AccessLog::where('type', 'login')
            ->where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->skip(1) // 現在のログインをスキップ
            ->first();
        
        $lastLoginAt = $previousLogin ? $previousLogin->created_at : null;

        $residenceTz = ResidenceTimezoneService::timezoneForResidence($user->residence);
        $lastLoginDisplay = $lastLoginAt
            ? Carbon::parse($lastLoginAt)->timezone($residenceTz)->format('Y-m-d H:i')
            : null;

        // 連続ログイン日数とコイン配布テーブルの情報を取得
        $consecutiveLoginDays = $user->consecutive_login_days ?? 0;
        $coinService = new \App\Services\CoinService();
        
        // コイン配布テーブルの情報を生成
        $coinRewardTable = [
            ['day' => 1, 'coins' => $coinService->calculateConsecutiveLoginReward(1)],
            ['day' => 2, 'coins' => $coinService->calculateConsecutiveLoginReward(2)],
            ['day' => 3, 'coins' => $coinService->calculateConsecutiveLoginReward(3)],
            ['day' => 4, 'coins' => $coinService->calculateConsecutiveLoginReward(4)],
            ['day' => 5, 'coins' => $coinService->calculateConsecutiveLoginReward(5)],
            ['day' => 6, 'coins' => $coinService->calculateConsecutiveLoginReward(6)],
            ['day' => 7, 'coins' => $coinService->calculateConsecutiveLoginReward(7)],
            ['day' => 8, 'coins' => $coinService->calculateConsecutiveLoginReward(8), 'note' => '4コインと5コインを交互に配布'],
            ['day' => 50, 'coins' => $coinService->calculateConsecutiveLoginReward(50), 'isBonus' => true],
            ['day' => 100, 'coins' => $coinService->calculateConsecutiveLoginReward(100), 'isBonus' => true],
            ['day' => 150, 'coins' => $coinService->calculateConsecutiveLoginReward(150), 'isBonus' => true],
            ['day' => 200, 'coins' => $coinService->calculateConsecutiveLoginReward(200), 'isBonus' => true],
        ];

        return view('profile.index', compact('user', 'threads', 'favoriteThreads', 'lang', 'totalCount', 'threadRestrictionData', 'threadImageReportScoreData', 'lastLoginDisplay', 'consecutiveLoginDays', 'coinRewardTable'))->with('hideSearch', true);
    }

    /**
     * プロフィール編集ページを表示
     */
    public function edit()
    {
        $user = Auth::user();
        
        // IDOR防止: 自分のプロフィールのみ編集可能
        Gate::authorize('update', $user);
        
        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return view('profile.edit', compact('user', 'lang'));
    }

    /**
     * プロフィールを更新
     */
    public function update(Request $request)
    {
        $user = Auth::user();
        
        // IDOR防止: 自分のプロフィールのみ更新可能
        Gate::authorize('update', $user);
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        // 通報による制限時でもプロフィール変更は許可（仕様変更）
        $messages = [
            'email.required' => \App\Services\LanguageService::trans('validation_email_required', $lang),
            'email.email' => \App\Services\LanguageService::trans('validation_email_email', $lang),
            'email.max' => \App\Services\LanguageService::trans('validation_email_max', $lang),
            'email.unique' => \App\Services\LanguageService::trans('validation_email_unique', $lang),
            'phone.required' => \App\Services\LanguageService::trans('validation_phone_required', $lang),
            'phone.string' => \App\Services\LanguageService::trans('validation_phone_string', $lang),
            'phone.max' => \App\Services\LanguageService::trans('validation_phone_max', $lang),
            'phone.unique' => \App\Services\LanguageService::trans('validation_phone_unique', $lang),
            'residence.required' => \App\Services\LanguageService::trans('validation_residence_required', $lang),
            'residence.string' => \App\Services\LanguageService::trans('validation_residence_string', $lang),
            'residence.in' => \App\Services\LanguageService::trans('validation_residence_in', $lang),
        ];
        
        $request->validate([
            'email' => 'required|email|max:255|unique:users,email,' . $user->user_id . ',user_id',
            'phone' => $phoneRule,
            'residence' => 'required|string|in:JP,US,GB,CA,AU,OTHER',
            'default_avatar' => ['nullable', 'string', 'regex:#^(none|(man|woman)\d+\.png)$#'],
            'language' => 'required|string|in:JA,EN',
        ], $messages);

        // 重複実行防止
        $lock = \App\Services\DuplicateSubmissionLockService::acquire('profile.update', $user->user_id);
        if (!$lock) {
            return back()->withErrors(['email' => \App\Services\LanguageService::trans('duplicate_submission', $lang)])->withInput();
        }
        try {

        // usernameとuser_identifierは変更不可（bioはUI廃止のためフォームから除外、DBカラムは残置）
        $emailChanged = $user->email !== $request->email;
        $phoneChanged = $user->phone !== $newPhone;

        // 連絡先を変えずに保存した場合、未完了の保留変更があれば破棄
        if (!$emailChanged && !$phoneChanged && ProfilePendingContactService::get($user->user_id)) {
            ProfilePendingContactService::clear($user->user_id);
        }

        if ($phoneChanged && $newPhone !== null) {
            $verificationResult = VeriphoneService::verifyPhone($newPhone);

            if (!$verificationResult['is_valid']) {
                return back()->withErrors(['phone' => \App\Services\LanguageService::trans('phone_number_not_usable', $lang)])->withInput();
            }
            if ($verificationResult['is_voip']) {
                return back()->withErrors(['phone' => \App\Services\LanguageService::trans('voip_number_not_allowed', $lang)])->withInput();
            }
        }

        $data = $request->only(['residence', 'language']);
        if (!$emailChanged) {
            $data['email'] = $request->email;
        }
        if (!$phoneChanged) {
            $data['phone'] = $newPhone;
        }

        // 言語設定が変更された場合、セッションキャッシュをクリア
        $oldLanguage = $user->language ?? 'JA';
        if ($oldLanguage !== $request->language) {
            session()->forget('current_language');
        }

        if ($emailChanged || $phoneChanged) {
            ProfilePendingContactService::clear($user->user_id);
        }

        // 居住地が変更された場合、履歴を記録
        if ($user->residence !== $request->residence) {
            ResidenceHistory::create([
                'user_id' => $user->user_id,
                'old_residence' => $user->residence,
                'new_residence' => $request->residence,
                'changed_at' => now(),
            ]);
        }

        // プロフィール画像の処理（デフォルト画像からの選択のみ）
        if ($request->has('default_avatar')) {
            if ($request->default_avatar === 'none') {
                // 非選択の場合、プロフィール画像をクリア
                // 古い画像を削除（デフォルト画像でない場合のみ）
                if ($user->profile_image && strpos($user->profile_image, 'avatars/') === false && strpos($user->profile_image, 'images/avatars/') === false) {
                    Storage::disk('public')->delete($user->profile_image);
                }
                $data['profile_image'] = null;
            } elseif ($request->default_avatar) {
                // デフォルト画像を選択
                // 古い画像を削除（デフォルト画像でない場合のみ）
                if ($user->profile_image && strpos($user->profile_image, 'avatars/') === false && strpos($user->profile_image, 'images/avatars/') === false) {
                    Storage::disk('public')->delete($user->profile_image);
                }
                
                // デフォルト画像のパスを設定
                $data['profile_image'] = 'images/avatars/' . $request->default_avatar;
            }
        }

        $user->update($data);

        if ($emailChanged || $phoneChanged) {
            $user->refresh();
            ProfilePendingContactService::put($user->user_id, [
                'email' => $emailChanged ? $request->email : null,
                'phone' => $phoneChanged ? $newPhone : null,
                'email_changed' => $emailChanged,
                'phone_changed' => $phoneChanged,
            ]);
        }

        // メールアドレスまたは電話番号が変更された場合、認証コードを生成して認証画面にリダイレクト
        if ($emailChanged && $phoneChanged) {
            $smsCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("sms_verification_user_{$user->user_id}", $smsCode, 300);
            \Log::info("プロフィール更新後のSMS認証コード: {$smsCode} (ユーザーID: {$user->user_id}, 保留電話: {$newPhone})");

            $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("email_verification_user_{$user->user_id}", $emailCode, 600);
            \Log::info("プロフィール更新後のメール認証コード: {$emailCode} (ユーザーID: {$user->user_id}, 保留メール: {$request->email})");

            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('profile.sms-verification')
                ->with('success', \App\Services\LanguageService::trans('profile_updated_reauth_both', $lang));
        } elseif ($emailChanged) {
            $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("email_verification_user_{$user->user_id}", $emailCode, 600);
            \Log::info("プロフィール更新後のメール認証コード: {$emailCode} (ユーザーID: {$user->user_id}, 保留メール: {$request->email})");

            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('profile.email-verification')
                ->with('success', \App\Services\LanguageService::trans('profile_updated_reauth_email', $lang));
        } elseif ($phoneChanged) {
            $smsCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("sms_verification_user_{$user->user_id}", $smsCode, 300);
            \Log::info("プロフィール更新後のSMS認証コード: {$smsCode} (ユーザーID: {$user->user_id}, 保留電話: {$newPhone})");

            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('profile.sms-verification')
                ->with('success', \App\Services\LanguageService::trans('profile_updated_reauth_sms', $lang));
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return redirect()->route('profile.index')->with('success', \App\Services\LanguageService::trans('profile_updated', $lang));
        } finally {
            $lock->release();
        }
    }

    /**
     * メール・電話変更の認証途中で戻る：保留と認証コードを破棄し DB は変更しない
     */
    public function cancelPendingContactVerification(Request $request)
    {
        $user = Auth::user();

        Gate::authorize('update', $user);

        if (!ProfilePendingContactService::get($user->user_id)) {
            return redirect()->route('profile.edit');
        }

        ProfilePendingContactService::clear($user->user_id);

        $lang = \App\Services\LanguageService::getCurrentLanguage();

        return redirect()->route('profile.edit')
            ->with('success', \App\Services\LanguageService::trans('profile_pending_contact_cancelled', $lang));
    }

    /**
     * ユーザープロフィールを表示（公開用）
     */
    public function show($userId)
    {
        // 実行時間制限を一時的に延長（パフォーマンス問題の回避）
        set_time_limit(60);
        
        $user = User::findOrFail($userId);
        
        // 国コードを日本語名に変換
        $user->residence_display = $this->getCountryName($user->residence);
        $user->nationality_display = $this->getCountryName($user->nationality);
        
        // ユーザーが作成したスレッドを取得（新しい順、最初の5件のみ）
        // user_idで直接検索してパフォーマンス向上
        $threadsQuery = \App\Models\Thread::where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc');
        
        $totalCount = $threadsQuery->count();
        $threads = $threadsQuery->take(5)->get();
        
        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        \App\Services\TranslationService::applyTranslatedThreadTitlesToCollection($threads, $lang);
        
        // スレッドの制限情報と画像通報スコアを取得
        $threadRestrictionData = [];
        $threadImageReportScoreData = [];
        if ($threads->isNotEmpty()) {
            // ThreadControllerのメソッドを使用して制限情報を取得
            $threadController = new \App\Http\Controllers\ThreadController();
            $threadRestrictionData = $threadController->getThreadRestrictionData($threads);
            
            $threadIds = $threads->pluck('thread_id')->toArray();
            foreach ($threadIds as $threadId) {
                $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
                // スレッド画像関連の通報理由で削除された場合は画像も非表示
                $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
                $threadImageReportScoreData[$threadId] = [
                    'score' => $score,
                    'isBlurred' => $score >= 1.0,
                    'isDeletedByImageReport' => $isDeletedByImageReport
                ];
            }
        }

        return view('profile.show', compact('user', 'threads', 'lang', 'totalCount', 'threadRestrictionData', 'threadImageReportScoreData'));
    }

    /**
     * 居住地変更履歴を取得（APIエンドポイント）
     */
    public function getResidenceHistory(Request $request, $userId)
    {
        // AJAXリクエストまたはJSONリクエストでない場合はユーザープロフィールページにリダイレクト
        // X-Requested-WithヘッダーまたはAccept: application/jsonヘッダーをチェック
        $isAjax = $request->ajax() || $request->wantsJson() || 
                  $request->header('X-Requested-With') === 'XMLHttpRequest' ||
                  $request->header('Accept') === 'application/json' ||
                  str_contains($request->header('Accept', ''), 'application/json');
        
        if (!$isAjax) {
            return redirect()->route('profile.show', $userId);
        }
        
        $user = User::findOrFail($userId);
        // IDOR防止: 公開プロフィールの履歴は誰でも閲覧可能（認可チェック不要）
        $histories = $user->residenceHistories()->get();
        
        return response()->json($histories);
    }

    /**
     * ユーザーが作成したスレッドをさらに取得（AJAX用）
     */
    public function getMoreThreads(Request $request, $userId = null)
    {
        // AJAXリクエストでない場合は適切なページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            if ($userId === null) {
                // ログインユーザーのマイページ
                if (Auth::check()) {
                    return redirect()->route('profile.index');
                } else {
                    return redirect()->route('login');
                }
            } else {
                // 指定されたユーザーのプロフィールページ
                return redirect()->route('profile.show', $userId);
            }
        }
        
        $offset = $request->get('offset', 0);
        $limit = 5;
        
        // ユーザーIDが指定されていない場合は、ログインユーザーを使用
        if ($userId === null) {
            if (!Auth::check()) {
                return response()->json(['error' => 'Unauthorized'], 401);
            }
            $user = Auth::user();
            // IDOR防止: 自分のスレッド一覧のみ取得可能
            Gate::authorize('viewThreads', $user);
        } else {
            $user = User::findOrFail($userId);
            // IDOR防止: 公開プロフィールのスレッド一覧は誰でも閲覧可能
            // ただし、ログインユーザーの場合は自分のスレッドのみ取得可能
            if (Auth::check() && Auth::user()->user_id === $user->user_id) {
                Gate::authorize('viewThreads', $user);
            }
        }
        
        $threadsQuery = \App\Models\Thread::where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit);
        
        $threads = $threadsQuery->get();
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        \App\Services\TranslationService::applyTranslatedThreadTitlesToCollection($threads, $lang);
        
        // スレッドの制限情報と画像通報スコアを取得
        $threadRestrictionData = [];
        $threadImageReportScoreData = [];
        if ($threads->isNotEmpty()) {
            // ThreadControllerのメソッドを使用して制限情報を取得
            $threadController = new \App\Http\Controllers\ThreadController();
            $threadRestrictionData = $threadController->getThreadRestrictionData($threads);
            
            $threadIds = $threads->pluck('thread_id')->toArray();
            foreach ($threadIds as $threadId) {
                $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
                // スレッド画像関連の通報理由で削除された場合は画像も非表示
                $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
                $threadImageReportScoreData[$threadId] = [
                    'score' => $score,
                    'isBlurred' => $score >= 1.0,
                    'isDeletedByImageReport' => $isDeletedByImageReport
                ];
            }
        }
        
        // HTMLを生成
        $html = view('threads.partials.thread-item-profile', compact('threads', 'lang', 'threadRestrictionData', 'threadImageReportScoreData'))->render();
        
        return response()->json([
            'html' => $html,
            'hasMore' => $threads->count() === $limit
        ]);
    }

    /**
     * ログアウト処理
     */
    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return redirect()->route('threads.index')->with('success', \App\Services\LanguageService::trans('logout_success', $lang));
    }
}
