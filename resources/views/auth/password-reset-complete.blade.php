@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('password_reset_new_password_title', $lang) }} - Chatgo</title>
    @include('layouts.favicon')
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('password_reset_new_password_title', $lang) }}</h1>
                <p>{{ \App\Services\LanguageService::trans('login_reset_new_password', $lang) }}</p>
            </div>

            <form method="POST" action="{{ route('login.password-reset.complete.submit') }}" class="auth-form">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <input type="hidden" name="email" value="{{ $email }}">

                <div class="form-group">
                    <label for="password">{{ \App\Services\LanguageService::trans('register_password_label', $lang) }} <span class="required">*</span></label>
                    <input type="password" id="password" name="password" required autocomplete="new-password">
                    <small class="form-help">{{ \App\Services\LanguageService::trans('register_password_help', $lang) }}</small>
                    <div id="password-requirements" class="password-requirements">
                        <div class="requirement" id="req-length">
                            <span class="requirement-icon">❌</span>
                            <span class="requirement-text">{{ \App\Services\LanguageService::trans('register_password_requirement_length', $lang) }}</span>
                        </div>
                        <div class="requirement" id="req-types">
                            <span class="requirement-icon">❌</span>
                            <span class="requirement-text">{{ \App\Services\LanguageService::trans('register_password_requirement_types', $lang) }}</span>
                        </div>
                        <div class="requirement" id="req-match">
                            <span class="requirement-icon">❌</span>
                            <span class="requirement-text">{{ \App\Services\LanguageService::trans('register_password_requirement_match', $lang) }}</span>
                        </div>
                    </div>
                    @error('password')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="password_confirmation">{{ \App\Services\LanguageService::trans('register_password_confirmation_label', $lang) }} <span class="required">*</span></label>
                    <input type="password" id="password_confirmation" name="password_confirmation" required autocomplete="new-password">
                </div>
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('save', $lang) }}</button>
            </form>

            <div class="auth-footer">
                <a href="{{ route('login') }}" class="back-link">{{ \App\Services\LanguageService::trans('login_back', $lang) }}</a>
            </div>
        </div>
    </div>
    <script src="{{ asset('js/auth-password-requirements.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
</body>
</html>
