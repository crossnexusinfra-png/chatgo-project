<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use App\Models\ResponseChangeLog;

class Response extends Model
{
    use HasFactory;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'response_id';

    /**
     * フォームからの入力を許可するカラムを指定します。
     *
     * @var array
     */
    protected $fillable = [
        'thread_id',
        'parent_response_id',
        'body',
        'source_lang',
        'user_id',
        'responses_num',
        'media_file',
        'media_type',
    ];

    /**
     * このレスポンスを作成したユーザーを取得します。
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * このレスポンスが属するスレッドを取得します。
     */
    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'thread_id');
    }

    /**
     * このレスポンスの返信先レスポンスを取得します。
     */
    public function parentResponse()
    {
        return $this->belongsTo(Response::class, 'parent_response_id', 'response_id');
    }

    /**
     * このレスポンスへの返信を取得します。
     */
    public function replies()
    {
        return $this->hasMany(Response::class, 'parent_response_id', 'response_id');
    }

    /**
     * このレスポンスの通報を取得します。
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'response_id', 'response_id');
    }

    /**
     * レスポンスが非表示にすべきかどうかを判定
     * 
     * @return bool
     */
    public function shouldBeHidden(): bool
    {
        // 特定理由によるスコア合計が1以上
        $restrictedScore = \App\Models\Report::calculateResponseRestrictedReasonScore($this->response_id);
        if ($restrictedScore >= 1.0) {
            return true;
        }
        
        // 「異なる思想」のスコア合計が3以上
        $ideologyScore = \App\Models\Report::calculateResponseIdeologyReportScore($this->response_id);
        if ($ideologyScore >= 3.0) {
            return true;
        }
        
        // 「成人向けコンテンツが含まれる」のスコア合計が2以上
        $adultContentScore = \App\Models\Report::calculateResponseAdultContentReportScore($this->response_id);
        if ($adultContentScore >= 2.0) {
            return true;
        }
        
        return false;
    }

    /**
     * 管理側で通報が了承され、公開側で削除扱いかどうか
     */
    public function isDeletedByReport(): bool
    {
        return \App\Models\Report::where('response_id', $this->response_id)
            ->where('is_approved', true)
            ->exists();
    }

    /**
     * レスポンスの制限理由を取得
     * 承認・未承認問わず、全ての通報理由を取得
     * 
     * @return array
     */
    public function getRestrictionReasons(): array
    {
        $sixMonthsAgo = now()->subMonths(6);
        $reasons = [];
        
        $restrictedReasons = [
            'スパム・迷惑行為',
            '攻撃的・不適切な内容',
            '不適切なリンク・外部誘導',
            '成人向け以外のコンテンツ規制違反',
            'その他'
        ];
        
        // 特定理由で半年以内の通報を取得（承認・未承認問わず）
        $reports = $this->reports()
            ->whereIn('reason', $restrictedReasons)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->get();
        
        foreach ($reports as $report) {
            if (!in_array($report->reason, $reasons)) {
                $reasons[] = $report->reason;
            }
        }
        
        // 「異なる思想」のスコア合計が3以上の場合も追加
        $ideologyScore = \App\Models\Report::calculateResponseIdeologyReportScore($this->response_id);
        if ($ideologyScore >= 3.0) {
            $reasons[] = '異なる思想に関しての意見の押し付け、妨害';
        }
        
        // 「成人向けコンテンツが含まれる」のスコア合計が2以上の場合も追加
        $adultContentScore = \App\Models\Report::calculateResponseAdultContentReportScore($this->response_id);
        if ($adultContentScore >= 2.0) {
            $reasons[] = '成人向けコンテンツが含まれる';
        }
        
        return array_unique($reasons);
    }

    /**
     * このレスポンスの変更ログを取得
     */
    public function changeLogs()
    {
        return $this->hasMany(ResponseChangeLog::class, 'response_id', 'response_id')
            ->orderBy('changed_at', 'desc');
    }

    /**
     * 非表示ログを記録
     * 
     * @param bool $isHidden 非表示かどうか
     * @param string|null $reason 理由
     * @return void
     */
    public function logHideStatus(bool $isHidden, ?string $reason = null): void
    {
        ResponseChangeLog::logHideStatus($this, $isHidden, $reason);
    }

    /**
     * ブートメソッド - モデルのイベントを設定
     */
    protected static function boot()
    {
        parent::boot();

        // レスポンスが作成された時にスレッドのレスポンス数を更新
        static::created(function ($response) {
            $response->thread->updateResponsesCountUp();
        });

        // レスポンスが削除された時にスレッドのレスポンス数を更新
        static::deleted(function ($response) {
            $response->thread->updateResponsesCountDown();
            // 削除ログを記録
            ResponseChangeLog::logDelete($response, 'レスポンスが削除されました');
        });
    }
}
