<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;

/**
 * SMS / メール認証コードをハッシュ化してキャッシュする。
 * 照合は Hash::check。デプロイ直前に残っている平文は有効期限まで hash_equals で受け付ける。
 * 画面表示用の平文は SHOW_VERIFICATION_CODE_ON_SCREEN のときだけセッションに置く。
 */
class VerificationCodeStore
{
    private const DEV_SESSION_KEY = 'dev_verification_plain';

    public static function put(string $key, string $plainCode, int $ttlSeconds): void
    {
        Cache::put($key, Hash::make($plainCode), $ttlSeconds);

        if (!config('app.show_verification_code_on_screen')) {
            return;
        }

        $map = session(self::DEV_SESSION_KEY, []);
        if (!is_array($map)) {
            $map = [];
        }
        $map[$key] = $plainCode;
        session([self::DEV_SESSION_KEY => $map]);
    }

    public static function matches(string $key, string $plainCode): bool
    {
        $stored = Cache::get($key);
        if (!is_string($stored) || $stored === '') {
            return false;
        }
        if (Hash::isHashed($stored)) {
            return Hash::check($plainCode, $stored);
        }

        return hash_equals($stored, $plainCode);
    }

    public static function exists(string $key): bool
    {
        return Cache::has($key);
    }

    public static function forget(string $key): void
    {
        Cache::forget($key);

        $map = session(self::DEV_SESSION_KEY, []);
        if (!is_array($map) || !array_key_exists($key, $map)) {
            return;
        }
        unset($map[$key]);
        session([self::DEV_SESSION_KEY => $map]);
    }

    public static function plainForDevScreen(string $key): ?string
    {
        if (!config('app.show_verification_code_on_screen')) {
            return null;
        }
        $map = session(self::DEV_SESSION_KEY, []);
        $value = is_array($map) ? ($map[$key] ?? null) : null;

        return is_string($value) && $value !== '' ? $value : null;
    }
}
