<?php

namespace App\Services;

/**
 * SMS（電話番号）認証の有効/無効を一元管理する。
 * 再有効化時は config/services.php の verification_enabled と .env の SMS_VERIFICATION_ENABLED を true にする。
 */
class SmsVerificationService
{
    public static function isEnabled(): bool
    {
        return (bool) config('services.sms.verification_enabled', false);
    }
}
