// auth-email-verification.js
// メール認証ページ用のJavaScript

(function() {
    'use strict';

    const config = window.authEmailVerificationConfig || {};
    const translations = config.translations || {};

    window.initAuthTimer({
        timeLeft: config.timeLeft || 600, // 10分
        resendTimeLeft: config.resendTimeLeft || 60, // 1分
        timerElementId: 'timer',
        resendTimerElementId: 'resendTimer',
        resendBtnId: 'resendBtn',
        translations: translations
    });
})();
