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
                <p>{{ \App\Services\LanguageService::trans('login_reset_new_password', $lang) }}</p>
            </div>

            <form method="POST" action="{{ route('login.password-reset.change.submit') }}" class="auth-form">
                @csrf
                <div class="form-group">
                    <label for="password">{{ \App\Services\LanguageService::trans('password', $lang) }} <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password">
                    @error('password')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">{{ \App\Services\LanguageService::trans('password_confirmation', $lang) }} <span class="required">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required minlength="8" autocomplete="new-password">
                </div>
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('save', $lang) }}</button>
            </form>

            <div class="auth-footer">
                <a href="{{ route('login') }}" class="back-link">{{ \App\Services\LanguageService::trans('login_back', $lang) }}</a>
            </div>
        </div>
    </div>
</body>
</html>
