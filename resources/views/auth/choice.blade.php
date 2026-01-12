@php
    // ViewComposerから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('auth_choice_title', $lang) }}</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('auth_choice_welcome', $lang) }}</h1>
                <p>{{ \App\Services\LanguageService::trans('auth_choice_subtitle', $lang) }}</p>
                
                @if(session('message'))
                    <div class="auth-message">
                        <p>{{ session('message') }}</p>
                        @if(app()->environment('local'))
                            <p><small>Debug: intended_url = {{ session('intended_url', 'not set') }}</small></p>
                            <p><small>Debug: session_id = {{ session()->getId() }}</small></p>
                            <p><small>Debug: all session data = {{ json_encode(session()->all()) }}</small></p>
                        @endif
                    </div>
                @endif
            </div>
            
            <div class="auth-buttons">
                <a href="{{ route('login') }}" class="auth-btn login-btn">
                    <span class="btn-icon">🔑</span>
                    <span class="btn-text">{{ \App\Services\LanguageService::trans('login_title', $lang) }}</span>
                </a>
                
                <a href="{{ route('auth.terms') }}" class="auth-btn register-btn">
                    <span class="btn-icon">📝</span>
                    <span class="btn-text">{{ \App\Services\LanguageService::trans('register_title', $lang) }}</span>
                </a>
            </div>
            
            <div class="auth-footer">
                <a href="{{ route('threads.index') }}" class="back-link">{{ \App\Services\LanguageService::trans('auth_choice_back_to_top', $lang) }}</a>
            </div>
        </div>
    </div>
</body>
</html>
