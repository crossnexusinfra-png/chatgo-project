@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
    $showDevLink = app()->environment('local') || config('app.show_verification_code_on_screen');
    $devUrl = session('password_reset_dev_url');
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('password_reset_sent_title', $lang) }} - BBS</title>
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('password_reset_sent_title', $lang) }}</h1>
                @if($byPhone ?? false)
                    <p>{{ \App\Services\LanguageService::trans('password_reset_sent_body_sms', $lang) }}</p>
                @else
                    <p>{{ \App\Services\LanguageService::trans('password_reset_sent_body_email', $lang) }}</p>
                @endif
            </div>

            @if($showDevLink && !empty($devUrl))
                <div class="dev-notice">
                    <h3>{{ \App\Services\LanguageService::trans('dev_environment_title', $lang) }}</h3>
                    <p>{{ \App\Services\LanguageService::trans('password_reset_dev_link_label', $lang) }}:</p>
                    <p><a href="{{ $devUrl }}"><strong>{{ $devUrl }}</strong></a></p>
                    <p><small>{{ \App\Services\LanguageService::trans('dev_environment_note', $lang) }}</small></p>
                </div>
            @endif

            <div class="auth-footer">
                <a href="{{ route('login') }}" class="back-link">{{ \App\Services\LanguageService::trans('login_back', $lang) }}</a>
            </div>
        </div>
    </div>
</body>
</html>
