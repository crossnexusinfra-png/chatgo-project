<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Session;

class ProfilePendingContactService
{
    public const SESSION_KEY = 'profile_pending_contact_verification';

    public static function get(?int $userId = null): ?array
    {
        $data = Session::get(self::SESSION_KEY);
        if (!is_array($data)) {
            return null;
        }
        if ($userId !== null && (int) ($data['user_id'] ?? 0) !== $userId) {
            return null;
        }

        return $data;
    }

    /**
     * @param  array{email?: ?string, phone?: ?string, email_changed: bool, phone_changed: bool}  $payload
     */
    public static function put(int $userId, array $payload): void
    {
        Session::put(self::SESSION_KEY, array_merge($payload, ['user_id' => $userId]));
    }

    public static function forgetVerificationCaches(int $userId): void
    {
        Cache::forget('sms_verification_user_' . $userId);
        Cache::forget('email_verification_user_' . $userId);
    }

    public static function clear(int $userId): void
    {
        $data = Session::get(self::SESSION_KEY);
        if (is_array($data) && (int) ($data['user_id'] ?? 0) === $userId) {
            Session::forget(self::SESSION_KEY);
        }
        self::forgetVerificationCaches($userId);
    }

    public static function displayEmail(\App\Models\User $user): string
    {
        $pending = self::get($user->user_id);

        return ($pending && !empty($pending['email_changed']) && isset($pending['email']))
            ? (string) $pending['email']
            : $user->email;
    }

    public static function displayPhone(\App\Models\User $user): string
    {
        $pending = self::get($user->user_id);

        return ($pending && !empty($pending['phone_changed']) && isset($pending['phone']))
            ? (string) $pending['phone']
            : (string) ($user->phone ?? '');
    }
}
