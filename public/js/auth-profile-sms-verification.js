// auth-profile-sms-verification.js
// プロフィールSMS認証ページ用のJavaScript

(function() {
    'use strict';

    const config = window.authProfileSmsVerificationConfig || {};
    const translations = config.translations || {};

    window.initAuthTimer({
        timeLeft: config.timeLeft || 300, // 5分
        resendTimeLeft: config.resendTimeLeft || 60, // 1分
        timerElementId: 'timer',
        resendTimerElementId: 'resendTimer',
        resendBtnId: 'resendBtn',
        translations: translations
    });
})();
