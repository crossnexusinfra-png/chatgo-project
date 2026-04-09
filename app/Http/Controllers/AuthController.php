<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use App\Models\User;
use App\Models\Admin;
use App\Services\PhoneNumberService;
use App\Services\VeriphoneService;
use App\Services\LoginFailureService;
use App\Mail\AbnormalLoginMail;
use App\Mail\PasswordResetLinkMail;
use App\Services\ProfilePendingContactService;

class AuthController extends Controller
{
    /**
     * ログイン画面を表示
     * 直前の入力メールで失敗回数・ロック状態を取得し、CAPTCHA・パスワード初期化リンク表示を制御
     */
    public function showLoginForm(Request $request)
    {
        $email = $request->old('email', '');
        $failureCount = $email !== '' ? LoginFailureService::getFailureCount($email) : 0;
        $lockExpiry = $email !== '' ? LoginFailureService::getLockExpiry($email) : null;
        $isLoginDisabled = $email !== '' && LoginFailureService::isLoginDisabled($email);
        $isLocked = $email !== '' && LoginFailureService::isLocked($email);
        $showCaptchaAndResetLink = $email !== '' && LoginFailureService::shouldShowCaptchaAndResetLink($email);

        return view('auth.login', [
            'failureCount' => $failureCount,
            'lockExpiry' => $lockExpiry,
            'isLoginDisabled' => $isLoginDisabled,
            'isLocked' => $isLocked,
            'showCaptchaAndResetLink' => $showCaptchaAndResetLink,
        ]);
    }

    /**
     * ログイン処理
     * 失敗回数に応じてロック・異常ログインメール・ログイン停止を適用（user_id＝メール単位）
     * IP は 20 req/min で throttle:login により制限
     */
    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email' => 'required|email|max:255',
            'password' => 'required|string|max:255',
        ]);

        $email = $credentials['email'];
        $clientIp = $request->ip();
        $country = $request->header('CF-IPCountry') ?: '—';

        // 50回以上失敗でログイン停止（パスワード初期化で解除）
        if (LoginFailureService::isLoginDisabled($email)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => \App\Services\LanguageService::trans('login_disabled_use_reset', $lang)]);
        }

        // ロック中は試行させない
        if (LoginFailureService::isLocked($email)) {
            $expiry = LoginFailureService::getLockExpiry($email);
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            $timeStr = $expiry ? $expiry->format('Y-m-d H:i') : '';
            return back()->withInput($request->only('email'))
                ->withErrors([
                    'email' => \App\Services\LanguageService::trans('login_locked', $lang, ['time' => $timeStr]),
                ]);
        }

        // まず通常のユーザーとしてログインを試みる
        if (Auth::attempt($credentials)) {
            $user = Auth::user();
            LoginFailureService::clearFailures($email);

            // セッションIDを再生成（セキュリティ対策）
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            // セッションに保存されたURLがあればそこにリダイレクト、なければトップページ
            $intendedUrl = session('intended_url', '/');
            \Log::info('AuthController login: intended_url', [
                'url' => $intendedUrl,
                'session_id' => session()->getId(),
                'all_session_data' => session()->all()
            ]);
            session()->forget('intended_url');

            // 承認待ちのレスポンスがあれば承認フラグを設定
            $allSessionData = session()->all();
            foreach ($allSessionData as $key => $value) {
                if (strpos($key, 'pending_acknowledge_response_') === 0) {
                    $responseId = str_replace('pending_acknowledge_response_', '', $key);
                    session(['acknowledged_response_' . $responseId => true]);
                    session()->forget($key);
                }
            }

            return redirect($intendedUrl);
        }

        // 管理者アカウントを確認
        $admin = Admin::where('email', $credentials['email'])->first();

        if ($admin && Hash::check($credentials['password'], $admin->password)) {
            $user = User::where('email', $credentials['email'])->first();

            if ($user) {
                LoginFailureService::clearFailures($email);
                Auth::login($user);

                if ($request->hasSession()) {
                    $request->session()->regenerate();
                }

                $intendedUrl = session('intended_url', '/');
                session()->forget('intended_url');

                $allSessionData = session()->all();
                foreach ($allSessionData as $key => $value) {
                    if (strpos($key, 'pending_acknowledge_response_') === 0) {
                        $responseId = str_replace('pending_acknowledge_response_', '', $key);
                        session(['acknowledged_response_' . $responseId => true]);
                        session()->forget($key);
                    }
                }

                return redirect($intendedUrl);
            }
        }

        // ログイン失敗: 失敗回数を増やし、閾値に応じてロック・異常メールを適用
        $count = LoginFailureService::incrementFailures($email);

        if ($count >= LoginFailureService::THRESHOLD_LOCK_12H) {
            LoginFailureService::setLockIfLonger($email, LoginFailureService::LOCK_MINUTES_12H);
        } elseif ($count >= LoginFailureService::THRESHOLD_LOCK_30) {
            LoginFailureService::setLockIfLonger($email, LoginFailureService::LOCK_MINUTES_30);
            if (!LoginFailureService::hasSentAbnormalEmailForThreshold($email, LoginFailureService::THRESHOLD_LOCK_30)) {
                $this->sendAbnormalLoginMail($email, $clientIp, $country);
                LoginFailureService::markAbnormalEmailSent($email, LoginFailureService::THRESHOLD_LOCK_30);
            }
        } elseif ($count >= LoginFailureService::THRESHOLD_LOCK_10) {
            LoginFailureService::setLockIfLonger($email, LoginFailureService::LOCK_MINUTES_10);
            if (!LoginFailureService::hasSentAbnormalEmailForThreshold($email, LoginFailureService::THRESHOLD_LOCK_10)) {
                $this->sendAbnormalLoginMail($email, $clientIp, $country);
                LoginFailureService::markAbnormalEmailSent($email, LoginFailureService::THRESHOLD_LOCK_10);
            }
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return back()->withInput($request->only('email'))->withErrors([
            'email' => \App\Services\LanguageService::trans('login_failed', $lang),
        ]);
    }

    /**
     * 異常ログイン検知メールを送信（IP, Country, Time）
     */
    private function sendAbnormalLoginMail(string $toEmail, string $ip, string $country): void
    {
        try {
            Mail::to($toEmail)->send(new AbnormalLoginMail(
                $ip,
                $country,
                now()->format('Y-m-d H:i:s T')
            ));
        } catch (\Throwable $e) {
            \Log::warning('AuthController: Failed to send abnormal login email', [
                'email' => $toEmail,
                'error' => $e->getMessage(),
            ]);
        }
    }

    /**
     * パスワード再設定（メールアドレス入力）
     */
    public function showPasswordResetForm()
    {
        return view('auth.password-reset');
    }

    /**
     * メールにパスワード再設定用リンクを送信
     */
    public function requestPasswordResetEmail(Request $request)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $request->validate([
            'email' => 'required|email|max:255',
        ]);

        $user = User::where('email', $request->email)->first();
        if (!$user) {
            return back()->withInput($request->only('email'))
                ->withErrors([
                    'email' => \App\Services\LanguageService::trans('password_reset_email_not_registered', $lang),
                ]);
        }

        $devUrlForNextPage = null;
        $status = Password::sendResetLink(
            ['email' => $user->email],
            function (User $u, string $token) use ($lang, &$devUrlForNextPage) {
                $url = $this->passwordResetUrlForUser($u, $token);
                if (app()->environment('local') || config('app.show_verification_code_on_screen')) {
                    $devUrlForNextPage = $url;
                }
                try {
                    Mail::to($u->email)->send(new PasswordResetLinkMail($url, $lang));
                } catch (\Throwable $e) {
                    \Log::warning('AuthController: Failed to send password reset email', [
                        'email' => $u->email,
                        'error' => $e->getMessage(),
                    ]);
                }
                return Password::RESET_LINK_SENT;
            }
        );

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput($request->only('email'))
                ->withErrors(['email' => \App\Services\LanguageService::trans('password_reset_throttled', $lang)]);
        }

        return $this->redirectAfterPasswordResetLinkRequest($devUrlForNextPage);
    }

    /**
     * メールを忘れた場合：登録電話番号でSMS（ログ）に同じ再設定リンクを送る
     */
    public function showPasswordResetPhoneForm()
    {
        return view('auth.password-reset-phone');
    }

    /**
     * 電話番号でパスワード再設定リンクをSMS相当で通知（実装はログ出力）
     */
    public function requestPasswordResetPhone(Request $request)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $request->validate([
            'phone_country' => 'required|string|max:10',
            'phone_local' => 'required|string|max:20',
        ]);

        try {
            $internationalPhone = PhoneNumberService::convertToInternational(
                $request->phone_country,
                $request->phone_local
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['phone_country' => $e->getMessage()])->withInput();
        }

        $user = User::where('phone', $internationalPhone)->first();
        if (!$user) {
            return back()->withInput($request->only('phone_country', 'phone_local'))
                ->withErrors([
                    'phone_local' => \App\Services\LanguageService::trans('password_reset_phone_not_registered', $lang),
                ]);
        }

        $verificationResult = VeriphoneService::verifyPhone($internationalPhone);
        if (!$verificationResult['is_valid']) {
            return back()->withErrors(['phone_local' => \App\Services\LanguageService::trans('phone_number_not_usable', $lang)])->withInput();
        }
        if (!empty($verificationResult['is_voip'])) {
            return back()->withErrors(['phone_local' => \App\Services\LanguageService::trans('voip_number_not_allowed', $lang)])->withInput();
        }

        $devUrlForNextPage = null;
        $status = Password::sendResetLink(
            ['email' => $user->email],
            function (User $u, string $token) use ($internationalPhone, &$devUrlForNextPage) {
                $url = $this->passwordResetUrlForUser($u, $token);
                if (app()->environment('local') || config('app.show_verification_code_on_screen')) {
                    $devUrlForNextPage = $url;
                }
                \Log::info('Password reset link (SMS path)', [
                    'phone' => $internationalPhone,
                    'user_id' => $u->user_id,
                    'url' => $url,
                ]);
                return Password::RESET_LINK_SENT;
            }
        );

        if ($status === Password::RESET_THROTTLED) {
            return back()->withInput($request->only('phone_country', 'phone_local'))
                ->withErrors(['phone_local' => \App\Services\LanguageService::trans('password_reset_throttled', $lang)]);
        }

        return $this->redirectAfterPasswordResetLinkRequest($devUrlForNextPage, true);
    }

    /**
     * リンク送信完了（登録済みのメール／電話でのみ遷移）
     */
    public function showPasswordResetSent(Request $request)
    {
        if (!$request->session()->get('password_reset_generic_notice')) {
            return redirect()->route('login.password-reset');
        }

        return view('auth.password-reset-sent', [
            'byPhone' => (bool) $request->session()->get('password_reset_sent_via_phone'),
        ]);
    }

    /**
     * トークン付きリンクから新パスワード入力画面
     */
    public function showPasswordResetComplete(Request $request, string $token)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $email = $request->query('email');
        if (!is_string($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return redirect()->route('login.password-reset')
                ->withErrors(['email' => \App\Services\LanguageService::trans('password_reset_invalid_link', $lang)]);
        }

        return view('auth.password-reset-complete', [
            'token' => $token,
            'email' => $email,
        ]);
    }

    /**
     * トークン検証後に新パスワードを保存
     */
    public function submitPasswordResetFromToken(Request $request)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $messages = [
            'password.required' => \App\Services\LanguageService::trans('validation_password_required', $lang),
            'password.string' => \App\Services\LanguageService::trans('validation_password_string', $lang),
            'password.min' => \App\Services\LanguageService::trans('validation_password_min', $lang),
            'password.confirmed' => \App\Services\LanguageService::trans('validation_password_confirmed', $lang),
            'email.required' => \App\Services\LanguageService::trans('validation_email_required', $lang),
            'email.email' => \App\Services\LanguageService::trans('validation_email_email', $lang),
        ];

        $request->validate([
            'token' => 'required|string',
            'email' => 'required|email|max:255',
            'password' => 'required|string|min:16|confirmed',
        ], $messages);

        if (!$this->passwordMeetsComplexityRules($request->password)) {
            return back()->withErrors(['password' => \App\Services\LanguageService::trans('validation_password_complexity', $lang)])->withInput();
        }

        $resetUser = null;
        $status = Password::reset(
            $request->only('email', 'password', 'password_confirmation', 'token'),
            function (User $user, string $password) use (&$resetUser) {
                $user->forceFill(['password' => Hash::make($password)])->save();
                LoginFailureService::clearFailures($user->email);
                $resetUser = $user->fresh();
            }
        );

        if ($status === Password::PASSWORD_RESET) {
            if (!$resetUser) {
                return redirect()->route('login')->with('success', \App\Services\LanguageService::trans('login_reset_success', $lang));
            }

            Auth::login($resetUser);

            if ($request->hasSession()) {
                $request->session()->regenerate();
            }

            $intendedUrl = session('intended_url', '/');
            session()->forget('intended_url');

            $allSessionData = session()->all();
            foreach ($allSessionData as $key => $value) {
                if (strpos($key, 'pending_acknowledge_response_') === 0) {
                    $responseId = str_replace('pending_acknowledge_response_', '', $key);
                    session(['acknowledged_response_' . $responseId => true]);
                    session()->forget($key);
                }
            }

            return redirect($intendedUrl)->with('success', \App\Services\LanguageService::trans('login_reset_success', $lang));
        }

        if ($status === Password::INVALID_TOKEN) {
            return redirect()->route('login.password-reset')
                ->withErrors(['email' => \App\Services\LanguageService::trans('password_reset_token_invalid', $lang)]);
        }

        return back()->withErrors(['email' => \App\Services\LanguageService::trans('password_reset_try_again', $lang)])->withInput();
    }

    private function passwordResetUrlForUser(User $user, string $token): string
    {
        return route('login.password-reset.complete', [
            'token' => $token,
            'email' => $user->getEmailForPasswordReset(),
        ], true);
    }

    private function redirectAfterPasswordResetLinkRequest(?string $devUrl, bool $viaPhone = false)
    {
        session()->flash('password_reset_sent_via_phone', $viaPhone);
        if ($devUrl) {
            session()->flash('password_reset_dev_url', $devUrl);
        }

        return redirect()->route('login.password-reset.sent')
            ->with('password_reset_generic_notice', true);
    }

    private function passwordMeetsComplexityRules(string $password): bool
    {
        $characterTypes = 0;
        if (preg_match('/[a-z]/', $password)) {
            $characterTypes++;
        }
        if (preg_match('/[A-Z]/', $password)) {
            $characterTypes++;
        }
        if (preg_match('/\d/', $password)) {
            $characterTypes++;
        }
        if (preg_match('/[^a-zA-Z0-9]/', $password)) {
            $characterTypes++;
        }

        return $characterTypes >= 3;
    }

    /**
     * 利用規約同意画面を表示
     */
    public function showTermsForm()
    {
        return view('auth.terms');
    }

    /**
     * 利用規約同意処理
     */
    public function acceptTerms(Request $request)
    {
        $request->validate([
            'terms_agreed' => 'required|accepted',
        ]);

        // セッションに保存されたintended_urlを保持
        $intendedUrl = session('intended_url');
        if ($intendedUrl) {
            session(['intended_url' => $intendedUrl]);
        }

        return redirect()->route('register');
    }

    /**
     * 新規登録画面を表示
     */
    public function showRegisterForm()
    {
        return view('auth.register');
    }

    /**
     * 新規登録処理（詳細情報入力）
     */
    public function register(Request $request)
    {
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $messages = [
            'username.required' => \App\Services\LanguageService::trans('validation_username_required', $lang),
            'username.string' => \App\Services\LanguageService::trans('validation_username_string', $lang),
            'username.min' => \App\Services\LanguageService::trans('validation_username_min', $lang),
            'username.max' => \App\Services\LanguageService::trans('validation_username_max', $lang),
            'username.regex' => \App\Services\LanguageService::trans('validation_username_regex', $lang),
            'user_identifier.min' => \App\Services\LanguageService::trans('validation_user_identifier_min', $lang),
            'user_identifier.max' => \App\Services\LanguageService::trans('validation_user_identifier_max', $lang),
            'user_identifier.regex' => \App\Services\LanguageService::trans('validation_user_identifier_regex', $lang),
            'user_identifier.unique' => \App\Services\LanguageService::trans('validation_user_identifier_unique', $lang),
            'phone_country.required' => \App\Services\LanguageService::trans('validation_phone_country_required', $lang),
            'phone_country.string' => \App\Services\LanguageService::trans('validation_phone_country_string', $lang),
            'phone_country.max' => \App\Services\LanguageService::trans('validation_phone_country_max', $lang),
            'phone_local.required' => \App\Services\LanguageService::trans('validation_phone_local_required', $lang),
            'phone_local.string' => \App\Services\LanguageService::trans('validation_phone_local_string', $lang),
            'phone_local.max' => \App\Services\LanguageService::trans('validation_phone_local_max', $lang),
            'email.required' => \App\Services\LanguageService::trans('validation_email_required', $lang),
            'email.string' => \App\Services\LanguageService::trans('validation_email_email', $lang),
            'email.email' => \App\Services\LanguageService::trans('validation_email_email', $lang),
            'email.max' => \App\Services\LanguageService::trans('validation_email_max', $lang),
            'email.unique' => \App\Services\LanguageService::trans('validation_email_unique', $lang),
            'password.required' => \App\Services\LanguageService::trans('validation_password_required', $lang),
            'password.string' => \App\Services\LanguageService::trans('validation_password_string', $lang),
            'password.min' => \App\Services\LanguageService::trans('validation_password_min', $lang),
            'password.confirmed' => \App\Services\LanguageService::trans('validation_password_confirmed', $lang),
            'birthdate.required' => \App\Services\LanguageService::trans('validation_birthdate_required', $lang),
            'birthdate.date' => \App\Services\LanguageService::trans('validation_birthdate_date', $lang),
            'birthdate.before' => \App\Services\LanguageService::trans('validation_birthdate_before', $lang),
            'nationality.required' => \App\Services\LanguageService::trans('validation_nationality_required', $lang),
            'nationality.string' => \App\Services\LanguageService::trans('validation_nationality_string', $lang),
            'nationality.in' => \App\Services\LanguageService::trans('validation_nationality_in', $lang),
            'residence.required' => \App\Services\LanguageService::trans('validation_residence_required', $lang),
            'residence.string' => \App\Services\LanguageService::trans('validation_residence_string', $lang),
            'residence.in' => \App\Services\LanguageService::trans('validation_residence_in', $lang),
        ];
        
        // ユーザー名のバリデーション（5-10文字、禁止文字チェック）
        $username = $request->username ?? '';
        if (mb_strlen($username) < 5 || mb_strlen($username) > 10) {
            return back()->withErrors(['username' => \App\Services\LanguageService::trans('validation_username_length', $lang)])->withInput();
        }
        
        // 禁止文字チェック: @, /, \, <, >, &, ", ', スペース, 改行
        $forbiddenChars = ['@', '/', '\\', '<', '>', '&', '"', "'", ' ', "\n", "\r"];
        foreach ($forbiddenChars as $char) {
            if (strpos($username, $char) !== false) {
                return back()->withErrors(['username' => \App\Services\LanguageService::trans('validation_username_forbidden_chars', $lang)])->withInput();
            }
        }
        
        // user_identifierのバリデーション（オプション、設定されない場合はランダム生成）
        $userIdentifier = $request->user_identifier;
        if ($userIdentifier) {
            if (mb_strlen($userIdentifier) < 5 || mb_strlen($userIdentifier) > 15) {
                return back()->withErrors(['user_identifier' => \App\Services\LanguageService::trans('validation_user_identifier_length', $lang)])->withInput();
            }
            if (!preg_match('/^[a-z_]+$/', $userIdentifier)) {
                return back()->withErrors(['user_identifier' => \App\Services\LanguageService::trans('validation_user_identifier_regex', $lang)])->withInput();
            }
            if (User::where('user_identifier', $userIdentifier)->exists()) {
                return back()->withErrors(['user_identifier' => \App\Services\LanguageService::trans('validation_user_identifier_unique', $lang)])->withInput();
            }
        } else {
            // ランダムでuser_identifierを生成
            $userIdentifier = $this->generateUniqueUserIdentifier();
        }
        
        // メールアドレスの一意性と垢バンチェック
        $existingEmailUser = \App\Models\User::where('email', $request->email)->first();
        if ($existingEmailUser) {
            // 垢バンされたユーザーのメールアドレスは使用不可
            if ($existingEmailUser->is_permanently_banned) {
                return back()->withErrors(['email' => \App\Services\LanguageService::trans('banned_email_not_usable', $lang)])->withInput();
            }
        }
        
        $request->validate([
            'username' => 'required|string|min:5|max:10',
            'user_identifier' => 'nullable|string|min:5|max:15|regex:/^[a-z_]+$/|unique:users,user_identifier',
            'phone_country' => 'required|string|in:US,CA,GB,DE,FR,NL,BE,SE,FI,DK,NO,IS,AT,CH,IE,JP,KR,SG,AU,NZ',
            'phone_local' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:16|confirmed',
            'nationality' => 'required|string|in:US,CA,GB,DE,FR,NL,BE,SE,FI,DK,NO,IS,AT,CH,IE,JP,KR,SG,AU,NZ,OTHER',
            'residence' => 'required|string|in:US,CA,GB,DE,FR,NL,BE,SE,FI,DK,NO,IS,AT,CH,IE,JP,KR,SG,AU,NZ,OTHER',
            'birthdate' => 'required|date|before:today',
            'invite_code' => 'nullable|string|max:20|exists:users,invite_code',
        ], $messages);

        // パスワードの複雑性をチェック
        $password = $request->password;
        $characterTypes = 0;
        
        if (preg_match('/[a-z]/', $password)) $characterTypes++;
        if (preg_match('/[A-Z]/', $password)) $characterTypes++;
        if (preg_match('/\d/', $password)) $characterTypes++;
        if (preg_match('/[^a-zA-Z0-9]/', $password)) $characterTypes++;
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        if ($characterTypes < 3) {
            return back()->withErrors(['password' => \App\Services\LanguageService::trans('validation_password_complexity', $lang)])->withInput();
        }

        // 国内番号を国際表記に変換
        try {
            $internationalPhone = PhoneNumberService::convertToInternational(
                $request->phone_country,
                $request->phone_local
            );
        } catch (\InvalidArgumentException $e) {
            return back()->withErrors(['phone_country' => $e->getMessage()])->withInput();
        }

        // 電話番号の一意性をチェック
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $existingPhoneUser = \App\Models\User::where('phone', $internationalPhone)->first();
        if ($existingPhoneUser) {
            // 垢バンされたユーザーの電話番号は使用不可
            if ($existingPhoneUser->is_permanently_banned) {
                return back()->withErrors(['phone_local' => \App\Services\LanguageService::trans('banned_phone_not_usable', $lang)])->withInput();
            }
            return back()->withErrors(['phone_local' => \App\Services\LanguageService::trans('validation_phone_unique', $lang)])->withInput();
        }

        // Veriphone APIで電話番号を検証（VOIP番号を除外）
        \Log::info('電話番号検証開始', [
            'phone_country' => $request->phone_country,
            'phone_local' => $request->phone_local,
            'international_phone' => $internationalPhone,
        ]);
        
        $verificationResult = VeriphoneService::verifyPhone($internationalPhone);
        
        \Log::info('電話番号検証結果', [
            'international_phone' => $internationalPhone,
            'is_valid' => $verificationResult['is_valid'],
            'is_voip' => $verificationResult['is_voip'] ?? false,
            'message' => $verificationResult['message'] ?? '',
        ]);
        
        if (!$verificationResult['is_valid']) {
            return back()->withErrors(['phone_local' => \App\Services\LanguageService::trans('phone_number_not_usable', $lang)])->withInput();
        }
        
        if ($verificationResult['is_voip']) {
            return back()->withErrors(['phone_local' => \App\Services\LanguageService::trans('voip_number_not_allowed', $lang)])->withInput();
        }

        // 登録データをセッションに保存
        $registrationData = $request->only([
            'username', 'email', 'password', 'nationality', 'residence', 'birthdate', 'invite_code'
        ]);
        $registrationData['phone'] = $internationalPhone;
        $registrationData['password'] = Hash::make($request->password);
        $registrationData['user_identifier'] = $userIdentifier; // user_identifierを保存
        
        session(['registration_data' => $registrationData]);
        
        // intended_urlを保持
        $intendedUrl = session('intended_url');
        if ($intendedUrl) {
            session(['intended_url' => $intendedUrl]);
        }

        // SMS認証コードを生成して送信
        $smsCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("sms_verification_{$internationalPhone}", $smsCode, 300); // 5分間有効

        // 実際のSMS送信はここで実装（今回はログに出力）
        \Log::info("SMS認証コード: {$smsCode} (電話番号: {$internationalPhone})");

        return redirect()->route('register.sms-verification');
    }

    /**
     * SMS認証画面を表示
     */
    public function showSmsVerification()
    {
        if (!session('registration_data')) {
            return redirect()->route('register');
        }
        return view('auth.sms-verification');
    }

    /**
     * SMS認証処理
     */
    public function verifySms(Request $request)
    {
        $request->validate([
            'sms_code' => 'required|string|size:6',
        ]);

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $registrationData = session('registration_data');
        $phone = $registrationData['phone'];
        $cachedCode = Cache::get("sms_verification_{$phone}");

        if (!$cachedCode || $cachedCode !== $request->sms_code) {
            return back()->withErrors(['sms_code' => \App\Services\LanguageService::trans('verification_code_incorrect', $lang)]);
        }

        // SMS認証成功
        Cache::forget("sms_verification_{$phone}");
        session(['registration_data.sms_verified' => true]);

        // intended_urlを保持
        $intendedUrl = session('intended_url');
        if ($intendedUrl) {
            session(['intended_url' => $intendedUrl]);
        }

        // メール認証コードを生成して送信
        $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("email_verification_{$registrationData['email']}", $emailCode, 600); // 10分間有効

        // 実際のメール送信はここで実装（今回はログに出力）
        \Log::info("メール認証コード: {$emailCode} (メール: {$registrationData['email']})");

        return redirect()->route('register.email-verification');
    }

    /**
     * SMS認証コード再送信
     */
    public function resendSms(Request $request)
    {
        $registrationData = session('registration_data');
        if (!$registrationData) {
            return redirect()->route('register');
        }

        // Veriphone APIで電話番号を再検証（VOIP番号を除外）
        $verificationResult = VeriphoneService::verifyPhone($registrationData['phone']);
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        if (!$verificationResult['is_valid']) {
            return back()->withErrors(['sms_code' => \App\Services\LanguageService::trans('phone_number_not_usable', $lang)]);
        }
        if ($verificationResult['is_voip']) {
            return back()->withErrors(['sms_code' => \App\Services\LanguageService::trans('voip_number_not_allowed', $lang)]);
        }

        $smsCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("sms_verification_{$registrationData['phone']}", $smsCode, 300);

        \Log::info("SMS認証コード再送信: {$smsCode} (電話番号: {$registrationData['phone']})");

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return back()->with('success', \App\Services\LanguageService::trans('verification_code_resent', $lang));
    }

    /**
     * メール認証画面を表示
     */
    public function showEmailVerification()
    {
        if (!session('registration_data') || !session('registration_data.sms_verified')) {
            return redirect()->route('register');
        }
        return view('auth.email-verification');
    }

    /**
     * メール認証処理
     */
    public function verifyEmail(Request $request)
    {
        $request->validate([
            'email_code' => 'required|string|size:6',
        ]);

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $registrationData = session('registration_data');
        $email = $registrationData['email'];
        $cachedCode = Cache::get("email_verification_{$email}");

        if (!$cachedCode || $cachedCode !== $request->email_code) {
            return back()->withErrors(['email_code' => \App\Services\LanguageService::trans('verification_code_incorrect', $lang)]);
        }

        // メール認証成功 - ユーザーを作成
        Cache::forget("email_verification_{$email}");
        
        // intended_urlを取得（セッションクリア前に）
        $intendedUrl = session('intended_url', '/');
        \Log::info('AuthController register: intended_url', ['url' => $intendedUrl]);
        
        // 国籍から言語設定を自動決定（英語コードで保存）
        $language = $this->getLanguageFromNationality($registrationData['nationality']);
        
        // 招待コードを処理（登録時に入力されたコードをセッションから引き継ぐ）
        $inviter = null;
        $inviteCode = $registrationData['invite_code'] ?? $request->input('invite_code');
        if (!empty($inviteCode)) {
            $inviter = User::where('invite_code', $inviteCode)->first();
        }
        
        // 招待コードを生成
        $friendService = new \App\Services\FriendService();
        
        $user = User::create([
            'username' => $registrationData['username'],
            'user_identifier' => $registrationData['user_identifier'],
            'email' => $registrationData['email'],
            'phone' => $registrationData['phone'],
            'nationality' => $registrationData['nationality'],
            'residence' => $registrationData['residence'],
            'birthdate' => $registrationData['birthdate'],
            'password' => $registrationData['password'],
            'is_verified' => true,
            'email_verified_at' => now(),
            'language' => $language,
            'coins' => 0, // 初期コインは0
        ]);
        
        // 招待コードを生成
        $friendService->generateInviteCode($user);

        // 初回登録時お知らせを送信（管理者が設定している場合）
        \App\Services\WelcomeNotificationService::sendWelcomeTo($user);
        
        // 招待コードが有効な場合、コインを配布
        if ($inviter) {
            $coinService = new \App\Services\CoinService();
            $coinService->addCoins($inviter, 10);
            $coinService->addCoins($user, 10);
            
            // 招待記録を保存
            \App\Models\UserInvite::create([
                'inviter_id' => $inviter->user_id,
                'invitee_id' => $user->user_id,
                'invite_code' => $inviteCode,
                'coins_given' => true,
                'friend_request_auto_created' => false,
            ]);
        }

        // セッションをクリア
        session()->forget('registration_data');
        session()->forget('intended_url');

        // ログイン
        Auth::login($user);
        
        // セッションIDを再生成（セキュリティ対策）
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        // 承認待ちのレスポンスがあれば承認フラグを設定
        $allSessionData = session()->all();
        foreach ($allSessionData as $key => $value) {
            if (strpos($key, 'pending_acknowledge_response_') === 0) {
                $responseId = str_replace('pending_acknowledge_response_', '', $key);
                session(['acknowledged_response_' . $responseId => true]);
                session()->forget($key);
            }
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return redirect($intendedUrl)->with('success', \App\Services\LanguageService::trans('register_success', $lang));
    }

    /**
     * メール認証コード再送信
     */
    public function resendEmail(Request $request)
    {
        $registrationData = session('registration_data');
        if (!$registrationData || !session('registration_data.sms_verified')) {
            return redirect()->route('register');
        }

        $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("email_verification_{$registrationData['email']}", $emailCode, 600);

        \Log::info("メール認証コード再送信: {$emailCode} (メール: {$registrationData['email']})");

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return back()->with('success', \App\Services\LanguageService::trans('verification_code_resent', $lang));
    }

    /**
     * ログアウト処理
     */
    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/');
    }

    /**
     * 認証選択画面を表示
     */
    public function showAuthChoice(Request $request)
    {
        // URLパラメータからintended_urlを取得してセッションに保存
        if ($request->has('intended')) {
            session(['intended_url' => $request->get('intended')]);
            \Log::info('AuthController showAuthChoice: intended_url saved from URL param', [
                'url' => $request->get('intended'),
                'session_id' => session()->getId()
            ]);
        }
        
        return view('auth.choice');
    }

    /**
     * 既存ユーザー向けSMS認証画面を表示
     */
    public function showProfileSmsVerification(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // 直接アクセスかどうかを判定（リファラーがない、または認証関連のURLでない場合）
        $referer = $request->header('referer');
        $isDirectAccess = !$referer || 
            (!str_contains($referer, '/profile/sms-verification') && 
             !str_contains($referer, '/profile/edit') && 
             !str_contains($referer, '/profile'));
        
        // 直接アクセスの場合はマイページにリダイレクト
        if ($isDirectAccess) {
            return redirect()->route('profile.index');
        }
        
        $pending = ProfilePendingContactService::get($user->user_id);
        $needsPendingPhone = $pending && !empty($pending['phone_changed']);
        if (!$needsPendingPhone && $user->sms_verified_at !== null) {
            return redirect()->route('profile.index');
        }

        $displayPhone = ProfilePendingContactService::displayPhone($user);

        return view('auth.profile-sms-verification', compact('user', 'displayPhone'));
    }

    /**
     * 既存ユーザー向けSMS認証処理
     */
    public function verifyProfileSms(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 直接アクセスかどうかを判定（リファラーがない、または認証関連のURLでない場合）
        $referer = $request->header('referer');
        $isDirectAccess = !$referer || 
            (!str_contains($referer, '/profile/sms-verification') && 
             !str_contains($referer, '/profile/edit') && 
             !str_contains($referer, '/profile'));
        
        // 直接アクセスの場合はマイページにリダイレクト
        if ($isDirectAccess) {
            return redirect()->route('profile.index');
        }

        $request->validate([
            'sms_code' => 'required|string|size:6',
        ]);

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $user = Auth::user()->fresh();
        $cachedCode = Cache::get("sms_verification_user_{$user->user_id}");

        if (!$cachedCode || $cachedCode !== $request->sms_code) {
            return back()->withErrors(['sms_code' => \App\Services\LanguageService::trans('verification_code_incorrect', $lang)]);
        }

        // SMS認証成功
        Cache::forget("sms_verification_user_{$user->user_id}");

        $pending = ProfilePendingContactService::get($user->user_id);
        if ($pending && !empty($pending['phone_changed'])) {
            $user->phone = (string) $pending['phone'];
            $user->sms_verified_at = now();
            $user->save();

            $pending['phone_changed'] = false;
            $pending['phone'] = null;

            if (!empty($pending['email_changed'])) {
                ProfilePendingContactService::put($user->user_id, [
                    'email' => $pending['email'],
                    'phone' => null,
                    'email_changed' => true,
                    'phone_changed' => false,
                ]);
                if (!Cache::has("email_verification_user_{$user->user_id}")) {
                    $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
                    Cache::put("email_verification_user_{$user->user_id}", $emailCode, 600);
                    \Log::info("プロフィール更新後のメール認証コード: {$emailCode} (ユーザーID: {$user->user_id}, 保留メール: {$pending['email']})");
                }
                $lang = \App\Services\LanguageService::getCurrentLanguage();
                return redirect()->route('profile.email-verification')->with('success', \App\Services\LanguageService::trans('sms_verification_completed_next_email', $lang));
            }

            ProfilePendingContactService::clear($user->user_id);
            $user->is_verified = $user->email_verified_at !== null;
            $user->save();
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('profile.index')->with('success', \App\Services\LanguageService::trans('sms_verification_completed', $lang));
        }

        $user->sms_verified_at = now();

        if ($user->email_verified_at !== null) {
            $user->is_verified = true;
            $user->save();
            if ($request->hasSession()) {
                $request->session()->regenerate();
            }
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('profile.index')->with('success', \App\Services\LanguageService::trans('sms_verification_completed', $lang));
        }

        $user->save();

        if (!Cache::has("email_verification_user_{$user->user_id}")) {
            $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            Cache::put("email_verification_user_{$user->user_id}", $emailCode, 600);
            \Log::info("プロフィール更新後のメール認証コード: {$emailCode} (ユーザーID: {$user->user_id}, メール: {$user->email})");
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return redirect()->route('profile.email-verification')->with('success', \App\Services\LanguageService::trans('sms_verification_completed_next_email', $lang));
    }

    /**
     * 既存ユーザー向けSMS認証コード再送信
     */
    public function resendProfileSms(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 直接アクセスかどうかを判定（リファラーがない、または認証関連のURLでない場合）
        $referer = $request->header('referer');
        $isDirectAccess = !$referer || 
            (!str_contains($referer, '/profile/sms-verification') && 
             !str_contains($referer, '/profile/edit') && 
             !str_contains($referer, '/profile'));
        
        // 直接アクセスの場合はマイページにリダイレクト
        if ($isDirectAccess) {
            return redirect()->route('profile.index');
        }

        $user = Auth::user();
        
        $smsCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("sms_verification_user_{$user->user_id}", $smsCode, 300);

        $logPhone = ProfilePendingContactService::displayPhone($user);
        \Log::info("既存ユーザーSMS認証コード再送信: {$smsCode} (ユーザーID: {$user->user_id}, 電話番号: {$logPhone})");

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return back()->with('success', \App\Services\LanguageService::trans('verification_code_resent', $lang));
    }

    /**
     * 既存ユーザー向けメール認証画面を表示
     */
    public function showProfileEmailVerification(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();
        
        // 直接アクセスかどうかを判定（リファラーがない、または認証関連のURLでない場合）
        $referer = $request->header('referer');
        $isDirectAccess = !$referer || 
            (!str_contains($referer, '/profile/email-verification') && 
             !str_contains($referer, '/profile/sms-verification') && 
             !str_contains($referer, '/profile/edit') && 
             !str_contains($referer, '/profile'));
        
        // 直接アクセスの場合はマイページにリダイレクト
        if ($isDirectAccess) {
            return redirect()->route('profile.index');
        }
        
        $pending = ProfilePendingContactService::get($user->user_id);
        $needsPendingEmail = $pending && !empty($pending['email_changed']);
        if (!$needsPendingEmail && $user->email_verified_at !== null) {
            return redirect()->route('profile.index');
        }

        $displayEmail = ProfilePendingContactService::displayEmail($user);

        return view('auth.profile-email-verification', compact('user', 'displayEmail'));
    }

    /**
     * 既存ユーザー向けメール認証処理
     */
    public function verifyProfileEmail(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 直接アクセスかどうかを判定（リファラーがない、または認証関連のURLでない場合）
        $referer = $request->header('referer');
        $isDirectAccess = !$referer || 
            (!str_contains($referer, '/profile/email-verification') && 
             !str_contains($referer, '/profile/sms-verification') && 
             !str_contains($referer, '/profile/edit') && 
             !str_contains($referer, '/profile'));
        
        // 直接アクセスの場合はマイページにリダイレクト
        if ($isDirectAccess) {
            return redirect()->route('profile.index');
        }

        $request->validate([
            'email_code' => 'required|string|size:6',
        ]);

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $user = Auth::user()->fresh();
        $cachedCode = Cache::get("email_verification_user_{$user->user_id}");

        if (!$cachedCode || $cachedCode !== $request->email_code) {
            return back()->withErrors(['email_code' => \App\Services\LanguageService::trans('verification_code_incorrect', $lang)]);
        }

        // メール認証成功
        Cache::forget("email_verification_user_{$user->user_id}");

        $pending = ProfilePendingContactService::get($user->user_id);
        if ($pending && !empty($pending['email_changed'])) {
            $user->email = (string) $pending['email'];
            ProfilePendingContactService::clear($user->user_id);
        }

        $user->email_verified_at = now();

        if ($user->sms_verified_at !== null) {
            $user->is_verified = true;
        }

        $user->save();

        // セッションIDを再生成（セキュリティ対策）
        if ($request->hasSession()) {
            $request->session()->regenerate();
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return redirect()->route('profile.index')->with('success', \App\Services\LanguageService::trans('email_verification_completed', $lang));
    }

    /**
     * 既存ユーザー向けメール認証コード再送信
     */
    public function resendProfileEmail(Request $request)
    {
        if (!Auth::check()) {
            return redirect()->route('login');
        }

        // 直接アクセスかどうかを判定（リファラーがない、または認証関連のURLでない場合）
        $referer = $request->header('referer');
        $isDirectAccess = !$referer || 
            (!str_contains($referer, '/profile/email-verification') && 
             !str_contains($referer, '/profile/sms-verification') && 
             !str_contains($referer, '/profile/edit') && 
             !str_contains($referer, '/profile'));
        
        // 直接アクセスの場合はマイページにリダイレクト
        if ($isDirectAccess) {
            return redirect()->route('profile.index');
        }

        $user = Auth::user();

        $emailCode = str_pad(random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
        Cache::put("email_verification_user_{$user->user_id}", $emailCode, 600);

        $logEmail = ProfilePendingContactService::displayEmail($user);
        \Log::info("既存ユーザーメール認証コード再送信: {$emailCode} (ユーザーID: {$user->user_id}, メール: {$logEmail})");

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return back()->with('success', \App\Services\LanguageService::trans('verification_code_resent', $lang));
    }

    /**
     * 国籍から言語設定を取得
     */
    private function getLanguageFromNationality($nationality)
    {
        // 日本は日本語、英語圏（US, GB, CA, AU）とその他は英語（英語コードで返す）
        return $nationality === 'JP' ? 'JA' : 'EN';
    }

    /**
     * ユニークなuser_identifierを生成
     */
    private function generateUniqueUserIdentifier(): string
    {
        do {
            // 5-15文字のランダムな文字列を生成（小文字英語とアンダーバーのみ）
            $length = random_int(5, 15);
            $userIdentifier = '';
            $chars = 'abcdefghijklmnopqrstuvwxyz_';
            
            for ($i = 0; $i < $length; $i++) {
                $userIdentifier .= $chars[random_int(0, strlen($chars) - 1)];
            }
            
            // 既に存在するかチェック
            $exists = User::where('user_identifier', $userIdentifier)->exists();
        } while ($exists);
        
        return $userIdentifier;
    }
}
