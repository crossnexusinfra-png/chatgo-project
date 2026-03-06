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
        <div class="auth-card verification-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('login_reset_title', $lang) }}</h1>
                <p>{{ \App\Services\LanguageService::trans('login_reset_verify_sms_email', $lang) }}</p>
            </div>

            <form method="POST" action="{{ route('login.password-reset.verify.submit') }}" class="auth-form">
                @csrf
                <div class="form-group">
                    <label for="sms_code">{{ \App\Services\LanguageService::trans('verification_code_label', $lang) }} (SMS) <span class="required">*</span></label>
                    <input type="text" id="sms_code" name="sms_code" maxlength="6" pattern="[0-9]{6}" required autofocus>
                    @error('sms_code')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="email_code">{{ \App\Services\LanguageService::trans('verification_code_label', $lang) }} ({{ \App\Services\LanguageService::trans('email', $lang) }}) <span class="required">*</span></label>
                    <input type="text" id="email_code" name="email_code" maxlength="6" pattern="[0-9]{6}" required>
                    @error('email_code')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('verify_button', $lang) }}</button>
            </form>

            <div class="auth-footer">
                <a href="{{ route('login.password-reset') }}" class="back-link">{{ \App\Services\LanguageService::trans('login_back', $lang) }}</a>
            </div>
        </div>
    </div>
</body>
</html>
