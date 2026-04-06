// パスワード要件のリアルタイム表示（auth-register.js と同判定ロジック。変更時は両方を揃えること）
(function() {
    'use strict';

    document.addEventListener('DOMContentLoaded', function() {
        const passwordInput = document.getElementById('password');
        const passwordConfirmationInput = document.getElementById('password_confirmation');
        const reqLength = document.getElementById('req-length');
        const reqTypes = document.getElementById('req-types');
        const reqMatch = document.getElementById('req-match');

        function validatePassword() {
            if (!passwordInput) return;

            const password = passwordInput.value;
            const passwordConfirmation = passwordConfirmationInput ? passwordConfirmationInput.value : '';

            if (password.length === 0) {
                [reqLength, reqTypes, reqMatch].forEach(function(el) {
                    if (!el) return;
                    el.classList.remove('valid', 'invalid');
                    const icon = el.querySelector('.requirement-icon');
                    if (icon) icon.textContent = '❌';
                });
                return;
            }

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
        validatePassword();
    });
})();
