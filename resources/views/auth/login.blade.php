@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $showCaptchaAndResetLink = $showCaptchaAndResetLink ?? false;
    $isLoginDisabled = $isLoginDisabled ?? false;
    $isLocked = $isLocked ?? false;
    $lockExpiry = $lockExpiry ?? null;
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('login_title', $lang) }} - Chatgo</title>
    @include('layouts.favicon')
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('login_title', $lang) }}</h1>
                <p>{{ \App\Services\LanguageService::trans('login_subtitle', $lang) }}</p>
            </div>

            @if (session('success'))
                <div class="alert alert-success">{{ session('success') }}</div>
            @endif

            @if ($isLoginDisabled)
                <div class="alert alert-danger">
                    <p>{{ \App\Services\LanguageService::trans('login_disabled_use_reset', $lang) }}</p>
                    <p><a href="{{ route('login.password-reset') }}">{{ \App\Services\LanguageService::trans('login_password_reset_link', $lang) }}</a></p>
                </div>
            @else
                <div class="auth-buttons auth-buttons--compact">
                    <a href="{{ route('auth.provider.redirect', ['provider' => 'google']) }}?intent=login" class="auth-btn login-btn">
                        <span class="btn-text">{{ \App\Services\LanguageService::trans('login_google_button', $lang) }}</span>
                    </a>
                </div>
                @if ($isLocked && $lockExpiry)
                    <div class="alert alert-warning">
                        {{ \App\Services\LanguageService::trans('login_locked', $lang, ['time' => $lockExpiry->format('Y-m-d H:i')]) }}
                        <p><a href="{{ route('login.password-reset') }}">{{ \App\Services\LanguageService::trans('login_password_reset_link', $lang) }}</a></p>
                    </div>
                @endif
            <form method="POST" action="{{ route('login') }}" class="auth-form">
                @csrf
                <div class="form-group">
                    <label for="email">{{ \App\Services\LanguageService::trans('login_email_label', $lang) }}</label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="password">{{ \App\Services\LanguageService::trans('login_password_label', $lang) }}</label>
                    <input type="password" id="password" name="password" required>
                    @error('password')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                    <p class="auth-link-spacing-sm">
                        <a href="{{ route('login.password-reset') }}" class="back-link back-link--inline">{{ \App\Services\LanguageService::trans('login_forgot_password_link', $lang) }}</a>
                    </p>
                </div>

                @if ($showCaptchaAndResetLink)
                <div class="form-group captcha-reset-group">
                    <p class="captcha-notice">{{ \App\Services\LanguageService::trans('login_captcha_notice', $lang) }}</p>
                    <p><a href="{{ route('login.password-reset') }}">{{ \App\Services\LanguageService::trans('login_password_reset_link', $lang) }}</a></p>
                    {{-- CAPTCHA: reCAPTCHA v2 等をここに組み込む場合は #captcha-container に表示 --}}
                    <div id="captcha-container" class="captcha-container"></div>
                </div>
                @endif

                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('login_submit', $lang) }}</button>
            </form>
            @endif

            <div class="auth-footer">
                <p>{{ \App\Services\LanguageService::trans('login_no_account', $lang) }} <a href="{{ route('auth.terms') }}">{{ \App\Services\LanguageService::trans('login_register_link', $lang) }}</a></p>
                <a href="{{ route('auth.choice') }}" class="back-link">{{ \App\Services\LanguageService::trans('login_back', $lang) }}</a>
            </div>
        </div>
    </div>
</body>
</html>
