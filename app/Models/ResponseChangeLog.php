<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ResponseChangeLog extends Model
{
    use HasFactory;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'log_id';

    /**
     * テーブル名を指定
     */
    protected $table = 'response_change_logs';

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'response_id',
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
     * 対象レスポンスを取得
     */
    public function response()
    {
        return $this->belongsTo(Response::class, 'response_id', 'response_id');
    }

    /**
     * 変更を実行したユーザーを取得
     */
    public function changedBy()
    {
        return $this->belongsTo(User::class, 'changed_by_user_id', 'user_id');
    }

    /**
     * レスポンス削除ログを作成
     * 
     * @param Response $response 対象レスポンス
     * @param string|null $reason 理由
     * @return ResponseChangeLog
     */
    public static function logDelete(Response $response, ?string $reason = null): self
    {
        return self::create([
            'response_id' => $response->response_id,
            'action_type' => 'delete',
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason ?? 'レスポンスが削除されました',
            'changed_at' => now(),
        ]);
    }

    /**
     * レスポンス非表示ログを作成
     * 
     * @param Response $response 対象レスポンス
     * @param bool $isHidden 非表示かどうか
     * @param string|null $reason 理由
     * @return ResponseChangeLog
     */
    public static function logHideStatus(Response $response, bool $isHidden, ?string $reason = null): self
    {
        $actionType = $isHidden ? 'hide' : 'unhide';
        
        return self::create([
            'response_id' => $response->response_id,
            'action_type' => $actionType,
            'changed_by_user_id' => auth()->id(),
            'ip_address' => request()->ip(),
            'user_agent' => request()->userAgent(),
            'reason' => $reason,
            'metadata' => [
                'restricted_score' => \App\Models\Report::calculateResponseRestrictedReasonScore($response->response_id),
                'ideology_score' => \App\Models\Report::calculateResponseIdeologyReportScore($response->response_id),
                'adult_content_score' => \App\Models\Report::calculateResponseAdultContentReportScore($response->response_id),
            ],
            'changed_at' => now(),
        ]);
    }
}
