<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Contracts\Cache\Lock;

/**
 * ユーザー操作の重複送信を防止するためのロックサービス。
 * UIの送信ボタン無効化に加え、サーバー側でDB保存前に同一アクションの二重送信を弾く。
 * 同一ユーザーが同一アクションを短時間に複数回実行するのを防ぐ。
 */
class DuplicateSubmissionLockService
{
    /** ロックの有効時間（秒）※UIのボタン無効化と同様の条件で、DB保存前に弾くための時間 */
    private const LOCK_SECONDS = 5;

    /**
     * 指定アクション用のロックを取得する。
     * ロックを取得できた場合は Lock インスタンスを返し、取得できなかった場合は null を返す。
     *
     * @param string $action アクション名（例: thread.store, response.store）
     * @param int|string|null $userKey ユーザーID（認証時）。未認証の場合は session_id や IP など
     * @param string|null $resourceId リソースID（同一リソースへの連打防止用、省略可）
     * @return Lock|null ロックインスタンス。取得できない場合は null
     */
    public static function acquire(string $action, $userKey, ?string $resourceId = null): ?Lock
    {
        $key = self::buildKey($action, $userKey, $resourceId);
        $lock = Cache::lock($key, self::LOCK_SECONDS);

        if ($lock->get()) {
            return $lock;
        }

        return null;
    }

    /**
     * ロックキーを組み立てる。
     */
    private static function buildKey(string $action, $userKey, ?string $resourceId = null): string
    {
        $parts = ['submission_lock', $action, (string) $userKey];
        if ($resourceId !== null && $resourceId !== '') {
            $parts[] = $resourceId;
        }
        return implode(':', $parts);
    }
}
