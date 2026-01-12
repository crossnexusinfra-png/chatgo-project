@php
    // ViewComposerから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('terms_title', $lang) }}</title>
    <link href="{{ asset('css/app.css') }}" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card terms-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('terms_header', $lang) }}</h1>
                <p>{{ \App\Services\LanguageService::trans('terms_subtitle', $lang) }}</p>
            </div>
            
            <div class="terms-content">
                <div class="terms-section">
                    <h3>{{ \App\Services\LanguageService::trans('terms_section_title', $lang) }}</h3>
                    <div class="terms-text">
                        <p>{{ \App\Services\LanguageService::trans('terms_rule_1', $lang) }}</p>
                        <p>{{ \App\Services\LanguageService::trans('terms_rule_2', $lang) }}</p>
                        <ul>
                            <li>{{ \App\Services\LanguageService::trans('terms_rule_2_1', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_rule_2_2', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_rule_2_3', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_rule_2_4', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_rule_2_5', $lang) }}</li>
                        </ul>
                        <p>{{ \App\Services\LanguageService::trans('terms_rule_3', $lang) }}</p>
                        <p>{{ \App\Services\LanguageService::trans('terms_rule_4', $lang) }}</p>
                    </div>
                </div>
                
                <div class="terms-section">
                    <h3>{{ \App\Services\LanguageService::trans('terms_privacy_section_title', $lang) }}</h3>
                    <div class="terms-text">
                        <p>{{ \App\Services\LanguageService::trans('terms_privacy_1', $lang) }}</p>
                        <ul>
                            <li>{{ \App\Services\LanguageService::trans('terms_privacy_1_1', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_privacy_1_2', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_privacy_1_3', $lang) }}</li>
                        </ul>
                        <p>{{ \App\Services\LanguageService::trans('terms_privacy_2', $lang) }}</p>
                        <ul>
                            <li>{{ \App\Services\LanguageService::trans('terms_privacy_2_1', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_privacy_2_2', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_privacy_2_3', $lang) }}</li>
                            <li>{{ \App\Services\LanguageService::trans('terms_privacy_2_4', $lang) }}</li>
                        </ul>
                        <p>{{ \App\Services\LanguageService::trans('terms_privacy_3', $lang) }}</p>
                        <p>{{ \App\Services\LanguageService::trans('terms_privacy_4', $lang) }}</p>
                    </div>
                </div>
            </div>
            
            <form method="POST" action="{{ route('register.terms') }}" class="terms-form">
                @csrf
                <div class="terms-agreement">
                    <label class="terms-checkbox">
                        <input type="checkbox" name="terms_agreed" required>
                        <span class="checkmark"></span>
                        <span class="terms-text">{{ \App\Services\LanguageService::trans('terms_agree_text', $lang) }}</span>
                    </label>
                </div>
                
                <div class="terms-actions">
                    <a href="{{ route('auth.choice') }}" class="auth-btn back-btn">{{ \App\Services\LanguageService::trans('terms_back', $lang) }}</a>
                    <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('terms_submit', $lang) }}</button>
                </div>
            </form>
        </div>
    </div>
</body>
</html>
