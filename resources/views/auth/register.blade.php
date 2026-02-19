@php
    // ViewComposerから渡された$langを使用、なければ取得
    $lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
@endphp
<!DOCTYPE html>
<html lang="{{ $lang }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>{{ \App\Services\LanguageService::trans('register_title', $lang) }} - BBS</title>
    <link href="{{ asset('css/app.css') }}?v=2" rel="stylesheet">
    <link href="{{ asset('css/bbs.css') }}" rel="stylesheet">
</head>
<body>
    <div class="auth-container">
        <div class="auth-card register-card">
            <div class="auth-header">
                <h1>{{ \App\Services\LanguageService::trans('register_title', $lang) }}</h1>
                <p>{{ \App\Services\LanguageService::trans('register_subtitle', $lang) }}</p>
            </div>
            
            @if ($errors->any())
                <div class="alert alert-danger">
                    <h4>{{ \App\Services\LanguageService::trans('register_validation_errors', $lang) }}</h4>
                    <ul class="mb-0">
                        @foreach ($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif
            
            <form method="POST" action="{{ route('register') }}" class="auth-form" id="registerForm">
                @csrf
                
                <div class="form-group">
                    <label for="username">{{ \App\Services\LanguageService::trans('register_username_label', $lang) }} <span class="required">*</span></label>
                    <input type="text" id="username" name="username" value="{{ old('username') }}" 
                           minlength="5" maxlength="10"
                           title="{{ \App\Services\LanguageService::trans('register_username_help', $lang) }}" 
                           required autofocus>
                    <small class="form-help">{{ \App\Services\LanguageService::trans('register_username_help', $lang) }} {{ \App\Services\LanguageService::trans('register_username_length', $lang) }}</small>
                    @error('username')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="user_identifier">{{ \App\Services\LanguageService::trans('register_user_identifier_label', $lang) }}</label>
                    <input type="text" id="user_identifier" name="user_identifier" value="{{ old('user_identifier') }}" 
                           minlength="5" maxlength="15"
                           pattern="[a-z_]+"
                           title="{{ \App\Services\LanguageService::trans('register_user_identifier_help', $lang) }}">
                    <small class="form-help">{{ \App\Services\LanguageService::trans('register_user_identifier_help', $lang) }} {{ \App\Services\LanguageService::trans('register_user_identifier_length', $lang) }}</small>
                    @error('user_identifier')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="phone_country">{{ \App\Services\LanguageService::trans('register_phone_country_label', $lang) }} <span class="required">*</span></label>
                    <x-country-select name="phone_country" id="phone_country" value="{{ old('phone_country') }}" required />
                    <small class="form-help">{{ \App\Services\LanguageService::trans('register_phone_country_help', $lang) }}</small>
                    @error('phone_country')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="phone_local">{{ \App\Services\LanguageService::trans('register_phone_local_label', $lang) }} <span class="required">*</span></label>
                    <div class="phone-input-container">
                        <span id="country-code-display" class="country-code-display">+81</span>
                        <input type="tel" id="phone_local" name="phone_local" value="{{ old('phone_local') }}" 
                               placeholder="{{ \App\Services\LanguageService::trans('register_phone_local_placeholder', $lang) }}" required>
                    </div>
                    <small class="form-help" id="phone-help">{{ \App\Services\LanguageService::trans('register_phone_local_help', $lang) }}</small>
                    @error('phone_local')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <!-- 隠しフィールドで国際表記の電話番号を送信 -->
                <input type="hidden" id="phone" name="phone" value="{{ old('phone') }}">
                
                <div class="form-group">
                    <label for="email">{{ \App\Services\LanguageService::trans('register_email_label', $lang) }} <span class="required">*</span></label>
                    <input type="email" id="email" name="email" value="{{ old('email') }}" required autocomplete="email">
                    @error('email')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
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
                
                <div class="form-group">
                    <label for="nationality">{{ \App\Services\LanguageService::trans('register_nationality_label', $lang) }} <span class="required">*</span></label>
                    <select id="nationality" name="nationality" required>
                        <option value="">{{ \App\Services\LanguageService::trans('select_please', $lang) }}</option>
                        <option value="US" {{ old('nationality') == 'US' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_usa', $lang) }}</option>
                        <option value="CA" {{ old('nationality') == 'CA' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_canada', $lang) }}</option>
                        <option value="GB" {{ old('nationality') == 'GB' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_uk', $lang) }}</option>
                        <option value="DE" {{ old('nationality') == 'DE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_de', $lang) }}</option>
                        <option value="FR" {{ old('nationality') == 'FR' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_fr', $lang) }}</option>
                        <option value="NL" {{ old('nationality') == 'NL' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_nl', $lang) }}</option>
                        <option value="BE" {{ old('nationality') == 'BE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_be', $lang) }}</option>
                        <option value="SE" {{ old('nationality') == 'SE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_se', $lang) }}</option>
                        <option value="FI" {{ old('nationality') == 'FI' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_fi', $lang) }}</option>
                        <option value="DK" {{ old('nationality') == 'DK' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_dk', $lang) }}</option>
                        <option value="NO" {{ old('nationality') == 'NO' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_no', $lang) }}</option>
                        <option value="IS" {{ old('nationality') == 'IS' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_is', $lang) }}</option>
                        <option value="AT" {{ old('nationality') == 'AT' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_at', $lang) }}</option>
                        <option value="CH" {{ old('nationality') == 'CH' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_ch', $lang) }}</option>
                        <option value="IE" {{ old('nationality') == 'IE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_ie', $lang) }}</option>
                        <option value="JP" {{ old('nationality') == 'JP' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_japan', $lang) }}</option>
                        <option value="KR" {{ old('nationality') == 'KR' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_kr', $lang) }}</option>
                        <option value="SG" {{ old('nationality') == 'SG' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_sg', $lang) }}</option>
                        <option value="AU" {{ old('nationality') == 'AU' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_australia', $lang) }}</option>
                        <option value="NZ" {{ old('nationality') == 'NZ' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_nz', $lang) }}</option>
                        <option value="OTHER" {{ old('nationality') == 'OTHER' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_other', $lang) }}</option>
                    </select>
                    @error('nationality')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="residence">{{ \App\Services\LanguageService::trans('register_residence_label', $lang) }} <span class="required">*</span></label>
                    <select id="residence" name="residence" required>
                        <option value="">{{ \App\Services\LanguageService::trans('select_please', $lang) }}</option>
                        <option value="US" {{ old('residence') == 'US' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_usa', $lang) }}</option>
                        <option value="CA" {{ old('residence') == 'CA' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_canada', $lang) }}</option>
                        <option value="GB" {{ old('residence') == 'GB' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_uk', $lang) }}</option>
                        <option value="DE" {{ old('residence') == 'DE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_de', $lang) }}</option>
                        <option value="FR" {{ old('residence') == 'FR' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_fr', $lang) }}</option>
                        <option value="NL" {{ old('residence') == 'NL' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_nl', $lang) }}</option>
                        <option value="BE" {{ old('residence') == 'BE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_be', $lang) }}</option>
                        <option value="SE" {{ old('residence') == 'SE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_se', $lang) }}</option>
                        <option value="FI" {{ old('residence') == 'FI' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_fi', $lang) }}</option>
                        <option value="DK" {{ old('residence') == 'DK' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_dk', $lang) }}</option>
                        <option value="NO" {{ old('residence') == 'NO' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_no', $lang) }}</option>
                        <option value="IS" {{ old('residence') == 'IS' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_is', $lang) }}</option>
                        <option value="AT" {{ old('residence') == 'AT' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_at', $lang) }}</option>
                        <option value="CH" {{ old('residence') == 'CH' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_ch', $lang) }}</option>
                        <option value="IE" {{ old('residence') == 'IE' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_ie', $lang) }}</option>
                        <option value="JP" {{ old('residence') == 'JP' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_japan', $lang) }}</option>
                        <option value="KR" {{ old('residence') == 'KR' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_kr', $lang) }}</option>
                        <option value="SG" {{ old('residence') == 'SG' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_sg', $lang) }}</option>
                        <option value="AU" {{ old('residence') == 'AU' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_australia', $lang) }}</option>
                        <option value="NZ" {{ old('residence') == 'NZ' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_nz', $lang) }}</option>
                        <option value="OTHER" {{ old('residence') == 'OTHER' ? 'selected' : '' }}>{{ \App\Services\LanguageService::trans('country_other', $lang) }}</option>
                    </select>
                    @error('residence')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="birthdate">{{ \App\Services\LanguageService::trans('register_birthdate_label', $lang) }} <span class="required">*</span></label>
                    <input type="date" id="birthdate" name="birthdate" value="{{ old('birthdate') }}" required>
                    <small class="form-help">{{ \App\Services\LanguageService::trans('register_birthdate_help', $lang) }}</small>
                    @error('birthdate')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <div class="form-group">
                    <label for="invite_code">{{ \App\Services\LanguageService::trans('register_invite_code_label', $lang) }}</label>
                    <input type="text" id="invite_code" name="invite_code" value="{{ old('invite_code') }}" 
                           placeholder="{{ \App\Services\LanguageService::trans('register_invite_code_placeholder', $lang) }}" 
                           maxlength="20" class="input-uppercase">
                    <small class="form-help">{{ \App\Services\LanguageService::trans('register_invite_code_help', $lang) }}</small>
                    @error('invite_code')
                        <span class="error-message">{{ $message }}</span>
                    @enderror
                </div>
                
                <button type="submit" class="auth-submit-btn">{{ \App\Services\LanguageService::trans('register_submit', $lang) }}</button>
            </form>
            
            <div class="auth-footer">
                <p>{{ \App\Services\LanguageService::trans('register_already_account', $lang) }} <a href="{{ route('login') }}">{{ \App\Services\LanguageService::trans('login', $lang) }}</a></p>
                <a href="{{ route('auth.terms') }}" class="back-link">{{ \App\Services\LanguageService::trans('register_back_to_terms', $lang) }}</a>
            </div>
        </div>
    </div>
    
    <script nonce="{{ $csp_nonce ?? '' }}">
        window.authRegisterConfig = {
            registering: '{{ \App\Services\LanguageService::trans("registering", $lang) }}',
            examplePrefix: '{{ \App\Services\LanguageService::trans("example_prefix", $lang) }}',
            defaultPhoneHelp: '{{ \App\Services\LanguageService::trans("register_phone_local_help", $lang) }}',
            countryData: {
                'US': { code: '+1', example: '555-123-4567', helpKey: 'register_phone_help_us' },
                'CA': { code: '+1', example: '555-123-4567', helpKey: 'register_phone_help_ca' },
                'GB': { code: '+44', example: '7123-456789', helpKey: 'register_phone_help_gb' },
                'DE': { code: '+49', example: '151-12345678', helpKey: 'register_phone_help_de' },
                'FR': { code: '+33', example: '06-12-34-56-78', helpKey: 'register_phone_help_fr' },
                'NL': { code: '+31', example: '6-12345678', helpKey: 'register_phone_help_nl' },
                'BE': { code: '+32', example: '470-12-34-56', helpKey: 'register_phone_help_be' },
                'SE': { code: '+46', example: '70-123-4567', helpKey: 'register_phone_help_se' },
                'FI': { code: '+358', example: '40-123-4567', helpKey: 'register_phone_help_fi' },
                'DK': { code: '+45', example: '20-12-34-56', helpKey: 'register_phone_help_dk' },
                'NO': { code: '+47', example: '412-34567', helpKey: 'register_phone_help_no' },
                'IS': { code: '+354', example: '612-3456', helpKey: 'register_phone_help_is' },
                'AT': { code: '+43', example: '664-123456', helpKey: 'register_phone_help_at' },
                'CH': { code: '+41', example: '76-123-45-67', helpKey: 'register_phone_help_ch' },
                'IE': { code: '+353', example: '85-123-4567', helpKey: 'register_phone_help_ie' },
                'JP': { code: '+81', example: '90-1234-5678', helpKey: 'register_phone_help_jp' },
                'KR': { code: '+82', example: '10-1234-5678', helpKey: 'register_phone_help_kr' },
                'SG': { code: '+65', example: '8123-4567', helpKey: 'register_phone_help_sg' },
                'AU': { code: '+61', example: '412-345-678', helpKey: 'register_phone_help_au' },
                'NZ': { code: '+64', example: '21-123-4567', helpKey: 'register_phone_help_nz' }
            },
            helpTexts: {
                @foreach(['US', 'CA', 'GB', 'DE', 'FR', 'NL', 'BE', 'SE', 'FI', 'DK', 'NO', 'IS', 'AT', 'CH', 'IE', 'JP', 'KR', 'SG', 'AU', 'NZ'] as $countryCode)
                '{{ $countryCode }}': '{{ \App\Services\LanguageService::trans("register_phone_help_" . strtolower($countryCode), $lang) }}',
                @endforeach
            },
            countryCodeMap: {
                '+1': 'US',
                '+44': 'GB',
                '+49': 'DE',
                '+33': 'FR',
                '+31': 'NL',
                '+32': 'BE',
                '+46': 'SE',
                '+358': 'FI',
                '+45': 'DK',
                '+47': 'NO',
                '+354': 'IS',
                '+43': 'AT',
                '+41': 'CH',
                '+353': 'IE',
                '+81': 'JP',
                '+82': 'KR',
                '+65': 'SG',
                '+61': 'AU',
                '+64': 'NZ'
            }
        };
    </script>
    <script src="{{ asset('js/auth-register.js') }}" nonce="{{ $csp_nonce ?? '' }}"></script>
</body>
</html>
