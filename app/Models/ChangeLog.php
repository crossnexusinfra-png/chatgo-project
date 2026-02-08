<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ChangeLog extends Model
{
    use HasFactory;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'change_log_id';

    /**
     * テーブル名を指定
     */
    protected $table = 'change_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'entity_type',
        'entity_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
        'changed_by_user_id',
        'reason',
        'metadata',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_value' => 'array',
            'new_value' => 'array',
            'metadata' => 'array',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 変更を実行したユーザーを取得
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id', 'user_id');
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
     * @param string $actionType アクションタイプ
     * @param string|null $fieldName フィールド名
     * @param mixed $oldValue 変更前の値
     * @param mixed $newValue 変更後の値
     * @param int|null $changedByUserId 変更実行者ID
     * @param string|null $reason 変更理由
     * @param array|null $metadata メタデータ
     * @return ChangeLog
     */
    public static function createLog(
        string $entityType,
        int $entityId,
        string $actionType,
        ?string $fieldName = null,
        $oldValue = null,
        $newValue = null,
        ?int $changedByUserId = null,
        ?string $reason = null,
        ?array $metadata = null
    ): self {
        return self::create([
            'entity_type' => $entityType,
            'entity_id' => $entityId,
            'action_type' => $actionType,
            'field_name' => $fieldName,
            'old_value' => $oldValue,
            'new_value' => $newValue,
            'changed_by_user_id' => $changedByUserId,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }
}
