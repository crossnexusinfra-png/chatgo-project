@php
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ \App\Services\LanguageService::trans('password_reset_phone_title', $lang) }} - Chatgo</title>
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('password_reset_phone_title', $lang) }}</h1>
                <p>{{ \App\Services\LanguageService::trans('password_reset_phone_intro', $lang) }}</p>
            </div>

            <form method="POST" action="{{ route('login.password-reset.phone.submit') }}" class="auth-form">
                @csrf
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
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('password_reset_send_link_sms', $lang) }}</button>
            </form>

            <div class="auth-footer">
                <a href="{{ route('login.password-reset') }}" class="back-link">← {{ \App\Services\LanguageService::trans('password_reset_back_to_email', $lang) }}</a>
            </div>
        </div>
    </div>
    <script nonce="{{ $csp_nonce ?? '' }}">
    (function() {
        var sel = document.getElementById('phone_country');
        var disp = document.getElementById('country-code-display');
        function sync() {
            if (!sel || !disp) return;
            var opt = sel.options[sel.selectedIndex];
            var code = opt && opt.getAttribute('data-country-code');
            disp.textContent = code || '+81';
        }
        if (sel) {
            sel.addEventListener('change', sync);
            document.addEventListener('DOMContentLoaded', sync);
        }
    })();
    </script>
</body>
</html>
