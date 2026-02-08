<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserChangeLog extends Model
{
    use HasFactory;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'log_id';

    /**
     * テーブル名を指定
     */
    protected $table = 'user_change_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'user_id',
        'action_type',
        'field_name',
        'old_value',
        'new_value',
        'changed_by_user_id',
        'ip_address',
        'user_agent',
        'reason',
        'metadata',
        'changed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metadata' => 'array',
            'changed_at' => 'datetime',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * 対象ユーザーを取得
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * 変更を実行したユーザーを取得
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id', 'user_id');
    }

    /**
     * ユーザー情報の変更ログを作成
     * 
     * @param User $user 対象ユーザー
     * @param string $fieldName フィールド名
     * @param mixed $oldValue 変更前の値
     * @param mixed $newValue 変更後の値
     * @param string|null $reason 理由
     * @return UserChangeLog
     */
    public static function logUpdate(
        User $user,
        string $fieldName,
        $oldValue,
        $newValue,
        ?string $reason = null
    ): self {
        return self::create([
            'user_id' => $user->user_id,
            'action_type' => 'update',
            'field_name' => $fieldName,
            'old_value' => $oldValue !== null ? (is_string($oldValue) ? $oldValue : json_encode($oldValue, JSON_UNESCAPED_UNICODE)) : null,
            'new_value' => $newValue !== null ? (is_string($newValue) ? $newValue : json_encode($newValue, JSON_UNESCAPED_UNICODE)) : null,
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'changed_at' => now(),
        ]);
    }

    /**
     * ユーザー削除ログを作成
     * 
     * @param User $user 対象ユーザー
     * @param string|null $reason 理由
     * @return UserChangeLog
     */
    public static function logDelete(User $user, ?string $reason = null): self
    {
        return self::create([
            'user_id' => $user->user_id,
            'action_type' => 'delete',
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason ?? 'ユーザーが削除されました',
            'changed_at' => now(),
        ]);
    }

    /**
     * ユーザー凍結ログを作成
     * 
     * @param User $user 対象ユーザー
     * @param \Carbon\Carbon|null $frozenUntil 凍結期限
     * @param string|null $reason 理由
     * @return UserChangeLog
     */
    public static function logFreeze(User $user, ?\Carbon\Carbon $frozenUntil = null, ?string $reason = null): self
    {
        $actionType = $frozenUntil ? 'freeze' : 'unfreeze';
        
        return self::create([
            'user_id' => $user->user_id,
            'action_type' => $actionType,
            'field_name' => 'frozen_until',
            'old_value' => $user->getOriginal('frozen_until') ? $user->getOriginal('frozen_until')->toDateTimeString() : null,
            'new_value' => $frozenUntil ? $frozenUntil->toDateTimeString() : null,
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'metadata' => [
                'freeze_count' => $user->freeze_count,
                'out_count' => $user->calculateOutCount(),
            ],
            'changed_at' => now(),
        ]);
    }

    /**
     * ユーザー永久凍結ログを作成
     * 
     * @param User $user 対象ユーザー
     * @param string|null $reason 理由
     * @return UserChangeLog
     */
    public static function logPermanentBan(User $user, ?string $reason = null): self
    {
        return self::create([
            'user_id' => $user->user_id,
            'action_type' => 'permanent_ban',
            'field_name' => 'is_permanently_banned',
            'old_value' => 'false',
            'new_value' => 'true',
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason ?? 'アウト数が4以上に達したため永久凍結',
            'metadata' => [
                'out_count' => $user->calculateOutCount(),
            ],
            'changed_at' => now(),
        ]);
    }

    /**
     * ユーザー非表示ログを作成
     * 
     * @param User $user 対象ユーザー
     * @param bool $isHidden 非表示かどうか
     * @param string|null $reason 理由
     * @return UserChangeLog
     */
    public static function logHideStatus(User $user, bool $isHidden, ?string $reason = null): self
    {
        $actionType = $isHidden ? 'hide' : 'unhide';
        
        return self::create([
            'user_id' => $user->user_id,
            'action_type' => $actionType,
            'field_name' => null,
            'old_value' => null,
            'new_value' => null,
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'metadata' => [
                'restricted_score' => \App\Models\Report::calculateUserProfileRestrictedReasonScore($user->user_id),
            ],
            'changed_at' => now(),
        ]);
    }
}
