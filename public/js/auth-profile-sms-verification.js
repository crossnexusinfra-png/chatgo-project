// auth-profile-sms-verification.js
// プロフィールSMS認証ページ用のJavaScript

(function() {
    'use strict';

    const configElement = document.getElementById('auth-profile-sms-verification-config');
    const translations = {
        expiredAlert: configElement ? (configElement.dataset.expiredAlert || '') : '',
        resendButton: configElement ? (configElement.dataset.resendButton || '') : ''
    };
    const timeLeft = configElement ? Number(configElement.dataset.timeLeft || 300) : 300;
    const resendTimeLeft = configElement ? Number(configElement.dataset.resendTimeLeft || 60) : 60;

    window.initAuthTimer({
        timeLeft: timeLeft, // 5分
        resendTimeLeft: resendTimeLeft, // 1分
        timerElementId: 'timer',
        resendTimerElementId: 'resendTimer',
        resendBtnId: 'resendBtn',
        translations: translations
    });
})();
