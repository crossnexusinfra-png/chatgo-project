@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('email_verification_title', $lang) }} - BBS</title>
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card verification-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('email_verification_header', $lang) }}</h1>
                @php
                    $registrationData = session('registration_data', []);
                    $email = $registrationData['email'] ?? '';
                    $showCode = app()->environment('local') || config('app.show_verification_code_on_screen');
                    $emailVerificationCode = $showCode ? Cache::get("email_verification_{$email}") : null;
                @endphp
                <p>{{ str_replace('{email}', $email, \App\Services\LanguageService::trans('email_verification_description', $lang)) }}</p>
                
                @if($showCode)
                <div class="dev-notice">
                    <h3>{{ \App\Services\LanguageService::trans('dev_environment_title', $lang) }}</h3>
                    <p>{{ \App\Services\LanguageService::trans('verification_code_label', $lang) }}: <strong>{{ $emailVerificationCode ?? \App\Services\LanguageService::trans('verification_code_not_available', $lang) }}</strong></p>
                    <p><small>{{ \App\Services\LanguageService::trans('dev_environment_note', $lang) }}</small></p>
                </div>
                @endif
            </div>
            
            <form method="POST" action="{{ route('register.email-verify') }}" class="auth-form">
                @csrf
                
                <div class="form-group">
                    <label for="email_code">{{ \App\Services\LanguageService::trans('verification_code_label', $lang) }} <span class="required">*</span></label>
                    <input type="text" id="email_code" name="email_code" 
                           placeholder="{{ \App\Services\LanguageService::trans('verification_code_placeholder', $lang) }}" 
                           maxlength="6" 
                           pattern="[0-9]{6}"
                           required autofocus>
                    <small class="form-help">{{ \App\Services\LanguageService::trans('verification_code_help', $lang) }}</small>
                    @error('email_code')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <div class="verification-timer">
                    <p>{{ \App\Services\LanguageService::trans('verification_code_expiry', $lang) }}: <span id="timer">10:00</span></p>
                </div>
                
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('verify_button', $lang) }}</button>
            </form>
            
            <div class="verification-actions">
                <form method="POST" action="{{ route('register.email-resend') }}" class="resend-form">
                    @csrf
                    <button type="submit" class="resend-btn" id="resendBtn" disabled>
                        {!! str_replace('{seconds}', '<span id="resendTimer">60</span>', \App\Services\LanguageService::trans('resend_verification_code', $lang)) !!}
                    </button>
                </form>
            </div>
            
            <div class="auth-footer">
                <a href="{{ route('register.sms-verification') }}" class="back-link">← {{ \App\Services\LanguageService::trans('back_to_sms_verification', $lang) }}</a>
            </div>
        </div>
    </div>
    
    <script nonce="{{ $csp_nonce ?? '' }}">
        window.authEmailVerificationConfig = {
            timeLeft: 600,
            resendTimeLeft: 60,
            translations: {
                expiredAlert: '{{ \App\Services\LanguageService::trans('verification_code_expired_alert', $lang) }}',
                resendButton: '{{ \App\Services\LanguageService::trans('resend_verification_code_button', $lang) }}'
            }
        };
    </script>
    <script src="{{ asset('js/auth-email-verification.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
</body>
</html>
