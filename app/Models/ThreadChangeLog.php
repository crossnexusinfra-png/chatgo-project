<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ThreadChangeLog extends Model
{
    use HasFactory;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'log_id';

    /**
     * テーブル名を指定
     */
    protected $table = 'thread_change_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'thread_id',
        'action_type',
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
     * 対象スレッドを取得
     */
    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'thread_id');
    }

    /**
     * 変更を実行したユーザーを取得
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id', 'user_id');
    }

    /**
     * スレッド削除ログを作成
     * 
     * @param Thread $thread 対象スレッド
     * @param string|null $reason 理由
     * @return ThreadChangeLog
     */
    public static function logDelete(Thread $thread, ?string $reason = null): self
    {
        return self::create([
            'thread_id' => $thread->thread_id,
            'action_type' => 'delete',
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason ?? 'スレッドが削除されました',
            'changed_at' => now(),
        ]);
    }

    /**
     * スレッド非表示ログを作成
     * 
     * @param Thread $thread 対象スレッド
     * @param bool $isHidden 非表示かどうか
     * @param string|null $reason 理由
     * @return ThreadChangeLog
     */
    public static function logHideStatus(Thread $thread, bool $isHidden, ?string $reason = null): self
    {
        $actionType = $isHidden ? 'hide' : 'unhide';
        
        return self::create([
            'thread_id' => $thread->thread_id,
            'action_type' => $actionType,
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'metadata' => [
                'restricted_score' => \App\Models\Report::calculateThreadRestrictedReasonScore($thread->thread_id),
                'ideology_score' => \App\Models\Report::calculateThreadIdeologyReportScore($thread->thread_id),
                'adult_content_score' => \App\Models\Report::calculateThreadAdultContentReportScore($thread->thread_id),
            ],
            'changed_at' => now(),
        ]);
    }
}
