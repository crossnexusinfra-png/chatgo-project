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
                <p>{{ \App\Services\LanguageService::trans('login_reset_enter_phone_email', $lang) }}</p>
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
                <div class="form-group">
                    <label for="phone_country">{{ \App\Services\LanguageService::trans('register_phone_country_label', $lang) }} <span class="required">*</span></label>
                    <x-country-select name="phone_country" id="phone_country" value="{{ old('phone_country') }}" required />
                    @error('phone_country')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <div class="form-group">
                    <label for="phone_local">{{ \App\Services\LanguageService::trans('register_phone_local_label', $lang) }} <span class="required">*</span></label>
                    <div class="phone-input-container">
                        <span id="country-code-display" class="country-code-display">+81</span>
                        <input type="tel" id="phone_local" name="phone_local" value="{{ old('phone_local') }}" required>
                    </div>
                    @error('phone_local')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('next', $lang) }}</button>
            </form>

            <div class="auth-footer">
                <a href="{{ route('login') }}" class="back-link">{{ \App\Services\LanguageService::trans('login_back', $lang) }}</a>
            </div>
        </div>
    </div>
</body>
</html>
