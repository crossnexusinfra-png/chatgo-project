<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

/**
 * ログイン失敗回数・ロック・異常ログインメール送信の管理
 * キーはメールアドレス（ログイン識別子）で、user_id 単位の制限として運用する。
 */
class LoginFailureService
{
    private const CACHE_PREFIX_FAILURES = 'login_failures:';
    private const CACHE_PREFIX_LOCK = 'login_lock:';
    private const CACHE_PREFIX_ABNORMAL_SENT = 'login_abnormal_sent:';
    private const FAILURES_DECAY_SECONDS = 86400 * 2; // 2日でリセット（未使用時）

    /** 5回失敗 → CAPTCHA + パスワード初期化リンク表示 */
    public const THRESHOLD_CAPTCHA = 5;
    /** 10回失敗 → 10分ロック + 異常ログインメール */
    public const THRESHOLD_LOCK_10 = 10;
    /** 20回失敗 → 30分ロック + 異常ログインメール */
    public const THRESHOLD_LOCK_30 = 20;
    /** 30回失敗 → 12時間ロック */
    public const THRESHOLD_LOCK_12H = 30;
    /** 50回失敗 → ログイン停止（パスワード初期化で解除） */
    public const THRESHOLD_DISABLED = 50;

    public const LOCK_MINUTES_10 = 10;
    public const LOCK_MINUTES_30 = 30;
    public const LOCK_MINUTES_12H = 12 * 60;

    private static function normalizeEmail(string $email): string
    {
        return strtolower(trim($email));
    }

    public static function getFailureCount(string $email): int
    {
        $key = self::CACHE_PREFIX_FAILURES . self::normalizeEmail($email);
        return (int) Cache::get($key, 0);
    }

    /**
     * 失敗回数を1増やし、新しい回数を返す
     */
    public static function incrementFailures(string $email): int
    {
        $normalized = self::normalizeEmail($email);
        $key = self::CACHE_PREFIX_FAILURES . $normalized;
        $count = (int) Cache::get($key, 0);
        $count++;
        Cache::put($key, $count, self::FAILURES_DECAY_SECONDS);
        return $count;
    }

    /**
     * 失敗回数・ロック・異常メール送信記録をクリア（パスワード初期化完了時など）
     */
    public static function clearFailures(string $email): void
    {
        $normalized = self::normalizeEmail($email);
        Cache::forget(self::CACHE_PREFIX_FAILURES . $normalized);
        Cache::forget(self::CACHE_PREFIX_LOCK . $normalized);
        Cache::forget(self::CACHE_PREFIX_ABNORMAL_SENT . $normalized);
    }

    public static function getLockExpiry(string $email): ?\DateTimeInterface
    {
        $key = self::CACHE_PREFIX_LOCK . self::normalizeEmail($email);
        $expiry = Cache::get($key);
        if ($expiry === null) {
            return null;
        }
        if ($expiry instanceof \DateTimeInterface) {
            return $expiry;
        }
        if (is_numeric($expiry)) {
            return \Carbon\Carbon::createFromTimestamp((int) $expiry);
        }
        return null;
    }

    /**
     * ロックを設定。既存のロックより長い場合のみ上書きする
     */
    public static function setLockIfLonger(string $email, int $lockMinutes): void
    {
        $normalized = self::normalizeEmail($email);
        $key = self::CACHE_PREFIX_LOCK . $normalized;
        $now = now();
        $newExpiry = $now->copy()->addMinutes($lockMinutes);
        $existing = Cache::get($key);
        if ($existing !== null) {
            $existingTs = $existing instanceof \DateTimeInterface ? $existing->getTimestamp() : (int) $existing;
            if ($existingTs > $newExpiry->getTimestamp()) {
                return; // 既存の方が長いので変更しない
            }
        }
        Cache::put($key, $newExpiry->getTimestamp(), self::FAILURES_DECAY_SECONDS);
    }

    public static function isLocked(string $email): bool
    {
        $expiry = self::getLockExpiry($email);
        return $expiry !== null && $expiry->getTimestamp() > time();
    }

    /** 50回以上失敗でログイン停止 */
    public static function isLoginDisabled(string $email): bool
    {
        return self::getFailureCount($email) >= self::THRESHOLD_DISABLED;
    }

    /** 5回以上で CAPTCHA + パスワード初期化リンク表示 */
    public static function shouldShowCaptchaAndResetLink(string $email): bool
    {
        return self::getFailureCount($email) >= self::THRESHOLD_CAPTCHA;
    }

    /**
     * 異常ログインメールをこの閾値で送信済みか
     */
    public static function hasSentAbnormalEmailForThreshold(string $email, int $threshold): bool
    {
        $key = self::CACHE_PREFIX_ABNORMAL_SENT . self::normalizeEmail($email);
        $sent = (int) Cache::get($key, 0);
        return $sent >= $threshold;
    }

    /**
     * 異常ログインメールを送信した旨を記録（閾値 10 または 20 で1回ずつ）
     */
    public static function markAbnormalEmailSent(string $email, int $threshold): void
    {
        $key = self::CACHE_PREFIX_ABNORMAL_SENT . self::normalizeEmail($email);
        $current = (int) Cache::get($key, 0);
        if ($threshold > $current) {
            Cache::put($key, $threshold, self::FAILURES_DECAY_SECONDS);
        }
    }
}
