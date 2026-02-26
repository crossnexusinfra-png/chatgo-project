// auth-register.js
// ユーザー登録ページ用のJavaScript

(function() {
    'use strict';

    const config = window.authRegisterConfig || {};
    const countryData = config.countryData || {};
    const registeringText = config.registering || '登録中';
    const helpTexts = config.helpTexts || {};
    const countryCodeMap = config.countryCodeMap || {};
    const examplePrefix = config.examplePrefix || '例:';
    const defaultPhoneHelp = config.defaultPhoneHelp || '';

    const countrySelect = document.getElementById('phone_country');
    const phoneLocalInput = document.getElementById('phone_local');
    const phoneHiddenInput = document.getElementById('phone');
    const countryCodeDisplay = document.getElementById('country-code-display');
    const phoneHelp = document.getElementById('phone-help');

    // 国際表記の電話番号を更新する関数
    function updateInternationalPhone() {
        if (phoneHiddenInput) {
            phoneHiddenInput.value = '';
        }
    }

    // 国選択時の処理
    if (countrySelect) {
        countrySelect.addEventListener('change', function() {
            const selectedCountry = this.value;
            const nationalitySelect = document.getElementById('nationality');
            const residenceSelect = document.getElementById('residence');
            
            if (selectedCountry && countryData[selectedCountry]) {
                const data = countryData[selectedCountry];
                if (countryCodeDisplay) {
                    countryCodeDisplay.textContent = data.code;
                }
                if (phoneLocalInput) {
                    phoneLocalInput.placeholder = `${examplePrefix} ${data.example}`;
                }
                if (phoneHelp) {
                    phoneHelp.textContent = helpTexts[selectedCountry] || defaultPhoneHelp;
                }
                
                // 国籍・居住地も自動設定
                if (nationalitySelect) {
                    nationalitySelect.value = selectedCountry;
                }
                if (residenceSelect) {
                    residenceSelect.value = selectedCountry;
                }
            } else {
                if (countryCodeDisplay) {
                    countryCodeDisplay.textContent = '+81';
                }
                if (phoneLocalInput) {
                    phoneLocalInput.placeholder = `${examplePrefix} 90-1234-5678`;
                }
                if (phoneHelp) {
                    phoneHelp.textContent = defaultPhoneHelp;
                }
            }
            
            updateInternationalPhone();
        });
    }

    // 国内番号入力時の処理
    if (phoneLocalInput) {
        phoneLocalInput.addEventListener('input', function() {
            updateInternationalPhone();
        });
    }

    // ページ読み込み時の初期化
    document.addEventListener('DOMContentLoaded', function() {
        if (countrySelect) {
            const selectedCountry = countrySelect.value;
            if (selectedCountry && countryData[selectedCountry]) {
                const data = countryData[selectedCountry];
                if (countryCodeDisplay) {
                    countryCodeDisplay.textContent = data.code;
                }
                if (phoneLocalInput) {
                    phoneLocalInput.placeholder = `${examplePrefix} ${data.example}`;
                }
                if (phoneHelp) {
                    phoneHelp.textContent = helpTexts[selectedCountry] || defaultPhoneHelp;
                }
            }
            updateInternationalPhone();
        }

        // 電話番号から国籍・居住地を自動判定
        const phoneInput = document.getElementById('phone');
        if (phoneInput) {
            phoneInput.addEventListener('input', function() {
                const phone = this.value;
                const nationalitySelect = document.getElementById('nationality');
                const residenceSelect = document.getElementById('residence');
                
                // 国コードから判定（長いコードから順にチェック）
                let matchedCountry = null;
                const sortedCodes = Object.keys(countryCodeMap).sort((a, b) => b.length - a.length);
                
                for (const code of sortedCodes) {
                    if (phone.startsWith(code)) {
                        matchedCountry = countryCodeMap[code];
                        break;
                    }
                }
                
                if (matchedCountry) {
                    if (nationalitySelect) {
                        nationalitySelect.value = matchedCountry;
                    }
                    if (residenceSelect) {
                        residenceSelect.value = matchedCountry;
                    }
                } else {
                    // その他の場合は「OTHER」に設定
                    if (nationalitySelect) {
                        nationalitySelect.value = 'OTHER';
                    }
                    if (residenceSelect) {
                        residenceSelect.value = 'OTHER';
                    }
                }
            });
        }

        // パスワード要件のリアルタイムバリデーション
        const passwordInput = document.getElementById('password');
        const passwordConfirmationInput = document.getElementById('password_confirmation');
        const reqLength = document.getElementById('req-length');
        const reqTypes = document.getElementById('req-types');
        const reqMatch = document.getElementById('req-match');
        
        function validatePassword() {
            if (!passwordInput) return;
            
            const password = passwordInput.value;
            const passwordConfirmation = passwordConfirmationInput ? passwordConfirmationInput.value : '';
            
            // パスワードが空の場合は初期状態を維持
            if (password.length === 0) {
                if (reqLength) {
                    reqLength.classList.remove('valid', 'invalid');
                    const icon = reqLength.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '❌';
                }
                if (reqTypes) {
                    reqTypes.classList.remove('valid', 'invalid');
                    const icon = reqTypes.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '❌';
                }
                if (reqMatch) {
                    reqMatch.classList.remove('valid', 'invalid');
                    const icon = reqMatch.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '❌';
                }
                return;
            }
            
            // 長さのチェック
            if (reqLength) {
                if (password.length >= 16) {
                    reqLength.classList.remove('invalid');
                    reqLength.classList.add('valid');
                    const icon = reqLength.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '✅';
                } else {
                    reqLength.classList.remove('valid');
                    reqLength.classList.add('invalid');
                    const icon = reqLength.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '❌';
                }
            }
            
            // 3種類以上のチェック
            let characterTypes = 0;
            if (/[a-z]/.test(password)) characterTypes++;
            if (/[A-Z]/.test(password)) characterTypes++;
            if (/\d/.test(password)) characterTypes++;
            if (/[^a-zA-Z0-9]/.test(password)) characterTypes++;
            
            if (reqTypes) {
                if (characterTypes >= 3) {
                    reqTypes.classList.remove('invalid');
                    reqTypes.classList.add('valid');
                    const icon = reqTypes.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '✅';
                } else {
                    reqTypes.classList.remove('valid');
                    reqTypes.classList.add('invalid');
                    const icon = reqTypes.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '❌';
                }
            }
            
            // 一致のチェック
            if (reqMatch) {
                if (passwordConfirmation && password === passwordConfirmation) {
                    reqMatch.classList.remove('invalid');
                    reqMatch.classList.add('valid');
                    const icon = reqMatch.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '✅';
                } else {
                    reqMatch.classList.remove('valid');
                    reqMatch.classList.add('invalid');
                    const icon = reqMatch.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '❌';
                }
            }
        }
        
        if (passwordInput) {
            passwordInput.addEventListener('input', validatePassword);
        }
        if (passwordConfirmationInput) {
            passwordConfirmationInput.addEventListener('input', validatePassword);
        }

        // 新規登録フォーム: 送信開始時にボタン無効化＋送信内容に関係する入力も無効化（二重送信防止）
        const registerForm = document.getElementById('registerForm');
        if (registerForm) {
            registerForm.addEventListener('submit', function(e) {
                const submitBtn = registerForm.querySelector('button[type="submit"]');
                if (submitBtn && submitBtn.disabled) {
                    e.preventDefault();
                    return false;
                }
                registerForm.classList.add('form-submitting');
                registerForm.querySelectorAll('input:not([type="hidden"]), textarea').forEach(function(el) {
                    el.readOnly = true;
                    el.setAttribute('aria-disabled', 'true');
                });
                registerForm.querySelectorAll('select').forEach(function(el) {
                    el.setAttribute('aria-disabled', 'true');
                });
                if (submitBtn) {
                    submitBtn.disabled = true;
                    submitBtn.textContent = registeringText;
                }
            });
        }
    });
})();
