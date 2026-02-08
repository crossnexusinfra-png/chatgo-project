<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ModificationLog extends Model
{
    use HasFactory;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'modification_log_id';

    /**
     * テーブル名を指定
     */
    protected $table = 'modification_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entity_type',
        'entity_id',
        'operation_type',
        'field_name',
        'old_value',
        'new_value',
        'performed_by_user_id',
        'ip_address',
        'user_agent',
        'description',
        'additional_data',
        'performed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'additional_data' => 'array',
            'performed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 操作を実行したユーザーを取得
     */
    public function performedBy()
    {
        return $this->belongsTo(User::class, 'performed_by_user_id', 'user_id');
    }

    /**
     * エンティティを取得（ポリモーフィックリレーション）
     */
    public function entity()
    {
        if ($this->entity_type === 'user') {
            return $this->belongsTo(User::class, 'entity_id', 'user_id');
        } elseif ($this->entity_type === 'thread') {
            return $this->belongsTo(Thread::class, 'entity_id', 'thread_id');
        }
        
        return null;
    }

    /**
     * 変更ログを作成する静的メソッド
     * 
     * @param string $entityType エンティティタイプ ('user' or 'thread')
     * @param int $entityId エンティティID
     * @param string $operationType 操作タイプ
     * @param string|null $fieldName フィールド名
     * @param mixed $oldValue 変更前の値
     * @param mixed $newValue 変更後の値
     * @param int|null $performedByUserId 操作実行者ID
     * @param string|null $description 操作の説明
     * @param array|null $additionalData 追加データ
     * @return ModificationLog
     */
    public static function createLog(
        string $entityType,
        int $entityId,
        string $operationType,
        ?string $fieldName = null,
        $oldValue = null,
        $newValue = null,
        ?int $performedByUserId = null,
        ?string $description = null,
        ?array $additionalData = null
    ): self {
        // IPアドレスとユーザーエージェントを取得
        $ipAddress = request()->ip();
        $userAgent = request()->userAgent();

        return self::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'operation_type' => $operationType,
            'field_name' => $fieldName,
            'old_value' => $oldValue !== null ? (is_string($oldValue) ? $oldValue : json_encode($oldValue, JSON_UNESCAPED_UNICODE)) : null,
            'new_value' => $newValue !== null ? (is_string($newValue) ? $newValue : json_encode($newValue, JSON_UNESCAPED_UNICODE)) : null,
            'performed_by_user_id' => $performedByUserId ?? auth()->id(),
            'ip_address' => $ipAddress,
            'user_agent' => $userAgent,
            'description' => $description,
            'additional_data' => $additionalData,
            'performed_at' => now(),
        ]);
    }

    /**
     * ユーザー情報の変更ログを記録
     * 
     * @param User $user ユーザー
     * @param string $fieldName フィールド名
     * @param mixed $oldValue 変更前の値
     * @param mixed $newValue 変更後の値
     * @param string|null $description 説明
     * @return ModificationLog
     */
    public static function logUserModification(
        User $user,
        string $fieldName,
        $oldValue,
        $newValue,
        ?string $description = null
    ): self {
        return self::createLog(
            'user',
            $user->user_id,
            'update',
            $fieldName,
            $oldValue,
            $newValue,
            auth()->id(),
            $description
        );
    }

    /**
     * スレッド情報の変更ログを記録
     * 
     * @param Thread $thread スレッド
     * @param string $fieldName フィールド名
     * @param mixed $oldValue 変更前の値
     * @param mixed $newValue 変更後の値
     * @param string|null $description 説明
     * @return ModificationLog
     */
    public static function logThreadModification(
        Thread $thread,
        string $fieldName,
        $oldValue,
        $newValue,
        ?string $description = null
    ): self {
        return self::createLog(
            'thread',
            $thread->thread_id,
            'update',
            $fieldName,
            $oldValue,
            $newValue,
            auth()->id(),
            $description
        );
    }

    /**
     * ユーザー削除ログを記録
     * 
     * @param User $user ユーザー
     * @param string|null $description 説明
     * @return ModificationLog
     */
    public static function logUserDeletion(User $user, ?string $description = null): self
    {
        return self::createLog(
            'user',
            $user->user_id,
            'delete',
            null,
            null,
            null,
            auth()->id(),
            $description ?? 'ユーザーが削除されました'
        );
    }

    /**
     * スレッド削除ログを記録
     * 
     * @param Thread $thread スレッド
     * @param string|null $description 説明
     * @return ModificationLog
     */
    public static function logThreadDeletion(Thread $thread, ?string $description = null): self
    {
        return self::createLog(
            'thread',
            $thread->thread_id,
            'delete',
            null,
            null,
            null,
            auth()->id(),
            $description ?? 'スレッドが削除されました'
        );
    }

    /**
     * ユーザー凍結ログを記録
     * 
     * @param User $user ユーザー
     * @param \Carbon\Carbon|null $frozenUntil 凍結期限
     * @param string|null $description 説明
     * @return ModificationLog
     */
    public static function logUserFreeze(User $user, ?\Carbon\Carbon $frozenUntil = null, ?string $description = null): self
    {
        $operationType = $frozenUntil ? 'freeze' : 'unfreeze';
        
        return self::createLog(
            'user',
            $user->user_id,
            $operationType,
            'frozen_until',
            $user->getOriginal('frozen_until'),
            $frozenUntil,
            auth()->id(),
            $description,
            [
                'freeze_count' => $user->freeze_count,
                'out_count' => $user->calculateOutCount(),
            ]
        );
    }

    /**
     * ユーザー永久凍結ログを記録
     * 
     * @param User $user ユーザー
     * @param string|null $description 説明
     * @return ModificationLog
     */
    public static function logUserPermanentBan(User $user, ?string $description = null): self
    {
        return self::createLog(
            'user',
            $user->user_id,
            'permanent_ban',
            'is_permanently_banned',
            false,
            true,
            auth()->id(),
            $description ?? 'アウト数が4以上に達したため永久凍結',
            [
                'out_count' => $user->calculateOutCount(),
            ]
        );
    }

    /**
     * 非表示ログを記録
     * 
     * @param string $entityType エンティティタイプ
     * @param int $entityId エンティティID
     * @param bool $isHidden 非表示かどうか
     * @param string|null $description 説明
     * @param array|null $additionalData 追加データ
     * @return ModificationLog
     */
    public static function logHideStatus(
        string $entityType,
        int $entityId,
        bool $isHidden,
        ?string $description = null,
        ?array $additionalData = null
    ): self {
        $operationType = $isHidden ? 'hide' : 'unhide';
        
        return self::createLog(
            $entityType,
            $entityId,
            $operationType,
            null,
            null,
            null,
            auth()->id(),
            $description,
            $additionalData
        );
    }
}
