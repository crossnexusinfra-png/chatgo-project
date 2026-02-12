@php
    // ViewComposerから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('login_title', $lang) }} - BBS</title>
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
                </div>
                
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('login_submit', $lang) }}</button>
            </form>
            
            <div class="auth-footer">
                <p>{{ \App\Services\LanguageService::trans('login_no_account', $lang) }} <a href="{{ route('auth.terms') }}">{{ \App\Services\LanguageService::trans('login_register_link', $lang) }}</a></p>
                <a href="{{ route('auth.choice') }}" class="back-link">{{ \App\Services\LanguageService::trans('login_back', $lang) }}</a>
            </div>
        </div>
    </div>
</body>
</html>
