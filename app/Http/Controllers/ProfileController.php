<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\ResidenceHistory;
use App\Models\AccessLog;
use App\Services\VeriphoneService;
use App\Services\SpamDetectionService;
use App\Services\SafeBrowsingService;

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
        
        $user = Auth::user();
        
        // IDOR防止: 自分のプロフィールのみ表示可能
        Gate::authorize('view', $user);
        
        // 国コードを日本語名に変換
        $user->residence_display = $this->getCountryName($user->residence);
        $user->nationality_display = $this->getCountryName($user->nationality);
        
        // ユーザーが作成したスレッドを取得（新しい順、最初の5件のみ）
        // user_idで直接検索してパフォーマンス向上
        $threadsQuery = \App\Models\Thread::where('user_id', $user->user_id)
            ->orderBy('created_at', 'desc');
        
        $totalCount = $threadsQuery->count();
        $threads = $threadsQuery->take(5)->get();
        
        // スレッドの制限情報と画像通報スコアを取得
        $threadRestrictionData = [];
        $threadImageReportScoreData = [];
        if ($threads->isNotEmpty()) {
            // ThreadControllerのメソッドを使用して制限情報を取得（メインページと統一）
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

        return view('profile.index', compact('user', 'threads', 'lang', 'totalCount', 'threadRestrictionData', 'threadImageReportScoreData', 'lastLoginAt', 'consecutiveLoginDays', 'coinRewardTable'))->with('hideSearch', true);
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
            'bio.string' => \App\Services\LanguageService::trans('validation_bio_string', $lang),
            'bio.max' => \App\Services\LanguageService::trans('validation_bio_max', $lang),
        ];
        
        $request->validate([
            'email' => 'required|email|max:255|unique:users,email,' . $user->user_id . ',user_id',
            'phone' => 'required|string|max:20|unique:users,phone,' . $user->user_id . ',user_id',
            'residence' => 'required|string|in:JP,US,GB,CA,AU,OTHER',
            'bio' => 'nullable|string|max:100',
            'default_avatar' => ['nullable', 'string', 'regex:#^(none|(man|woman)\d+\.png)$#'],
            'language' => 'required|string|in:JA,EN',
        ], $messages);

        // 自己紹介文（bio）のチェック
        if (!empty($request->bio)) {
            $bio = $request->bio;
            
            // URLチェック（URLが含まれている場合は拒否）
            $safeBrowsingService = new SafeBrowsingService();
            $urls = $safeBrowsingService->extractUrls($bio);
            if (!empty($urls)) {
                return back()->withInput()->withErrors(['bio' => \App\Services\LanguageService::trans('bio_url_not_allowed', $lang)]);
            }
            
            // NGワード完全一致チェック
            $spamDetectionService = new SpamDetectionService();
            $ngWordResult = $spamDetectionService->checkNgWords($bio);
            if ($ngWordResult['is_spam']) {
                return back()->withInput()->withErrors(['bio' => \App\Services\LanguageService::trans('spam_ng_word_detected', $lang)]);
            }
            
            // NGワード類似率チェック
            $similarityResult = $spamDetectionService->checkBioSimilarity($bio);
            if ($similarityResult['is_spam']) {
                return back()->withInput()->withErrors(['bio' => \App\Services\LanguageService::trans('spam_similar_response_detected', $lang)]);
            }
        }

        // usernameとuser_identifierは変更不可
        $data = $request->only(['email', 'phone', 'residence', 'bio', 'language']);

        // 言語設定が変更された場合、セッションキャッシュをクリア
        $oldLanguage = $user->language ?? 'JA';
        if ($oldLanguage !== $request->language) {
            session()->forget('current_language');
        }

        // 変更フラグを保持
        $emailChanged = false;
        $phoneChanged = false;

        // メールアドレスが変更された場合、メール認証をリセット
        if ($user->email !== $request->email) {
            $data['email_verified_at'] = null;
            $emailChanged = true;
        }

        // 電話番号が変更された場合、SMS認証をリセット
        if ($user->phone !== $request->phone) {
            // Veriphone APIで電話番号を検証（VOIP番号を除外）
            $verificationResult = VeriphoneService::verifyPhone($request->phone);
            
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            
            if (!$verificationResult['is_valid'] || $verificationResult['is_voip']) {
                return back()->withErrors(['phone' => \App\Services\LanguageService::trans('phone_number_not_usable', $lang)])->withInput();
            }
            
            $data['sms_verified_at'] = null;
            $phoneChanged = true;
        }

        // メールアドレスまたは電話番号が変更された場合、is_verifiedをfalseに設定
        if ($emailChanged || $phoneChanged) {
            $data['is_verified'] = false;
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

        // メールアドレスまたは電話番号が変更された場合、セッションIDを再生成（セキュリティ対策）
        if (($emailChanged || $phoneChanged) && $request->hasSession()) {
            $request->session()->regenerate();
        }

        // メールアドレスまたは電話番号が変更された場合、認証コードを生成して認証画面にリダイレクト
        if ($emailChanged && $phoneChanged) {
            // SMS認証コードを生成して送信
            $smsCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("sms_verification_user_{$user->user_id}", $smsCode, 300); // 5分間有効
            \Log::info("プロフィール更新後のSMS認証コード: {$smsCode} (ユーザーID: {$user->user_id}, 電話番号: {$user->phone})");

            // メール認証コードを生成して送信
            $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("email_verification_user_{$user->user_id}", $emailCode, 600); // 10分間有効
            \Log::info("プロフィール更新後のメール認証コード: {$emailCode} (ユーザーID: {$user->user_id}, メール: {$user->email})");

            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('profile.sms-verification')
                ->with('success', \App\Services\LanguageService::trans('profile_updated_reauth_both', $lang));
        } elseif ($emailChanged) {
            // メール認証コードを生成して送信
            $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("email_verification_user_{$user->user_id}", $emailCode, 600); // 10分間有効
            \Log::info("プロフィール更新後のメール認証コード: {$emailCode} (ユーザーID: {$user->user_id}, メール: {$user->email})");

            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('profile.email-verification')
                ->with('success', \App\Services\LanguageService::trans('profile_updated_reauth_email', $lang));
        } elseif ($phoneChanged) {
            // SMS認証コードを生成して送信
            $smsCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("sms_verification_user_{$user->user_id}", $smsCode, 300); // 5分間有効
            \Log::info("プロフィール更新後のSMS認証コード: {$smsCode} (ユーザーID: {$user->user_id}, 電話番号: {$user->phone})");

            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('profile.sms-verification')
                ->with('success', \App\Services\LanguageService::trans('profile_updated_reauth_sms', $lang));
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return redirect()->route('profile.index')->with('success', \App\Services\LanguageService::trans('profile_updated', $lang));
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
        
        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

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
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
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
