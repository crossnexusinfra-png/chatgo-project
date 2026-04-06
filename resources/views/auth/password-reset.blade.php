@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('login_reset_title', $lang) }} - BBS</title>
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('login_reset_title', $lang) }}</h1>
                <p>{{ \App\Services\LanguageService::trans('password_reset_email_intro', $lang) }}</p>
            </div>

            <form method="POST" action="{{ route('login.password-reset.request') }}" class="auth-form">
                @csrf
                <div class="form-group">
                    <label for="email">{{ \App\Services\LanguageService::trans('email', $lang) }} <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>
                    @error('email')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('password_reset_send_link', $lang) }}</button>
            </form>

            <p style="margin-top: 1rem;">
                <a href="{{ route('login.password-reset.phone') }}" class="back-link back-link--inline">{{ \App\Services\LanguageService::trans('password_reset_forgot_email_link', $lang) }}</a>
            </p>

            <div class="auth-footer">
                <a href="{{ route('login') }}" class="back-link">{{ \App\Services\LanguageService::trans('login_back', $lang) }}</a>
            </div>
        </div>
    </div>
</body>
</html>
