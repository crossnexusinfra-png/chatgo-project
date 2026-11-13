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
        'parent_original_response_id',
        'parent_snapshot_username',
        'parent_snapshot_body',
        'body',
        'source_lang',
        'user_id',
        'responses_num',
        'media_file',
        'media_type',
        'report_restricted',
        'deleted_by_report',
        'report_restriction_reasons',
    ];

    protected $casts = [
        'report_restricted' => 'boolean',
        'deleted_by_report' => 'boolean',
        'report_restriction_reasons' => 'array',
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
        return (bool) $this->report_restricted;
    }

    /**
     * 管理側で通報が了承され、公開側で削除扱いかどうか
     */
    public function isDeletedByReport(): bool
    {
        return (bool) $this->deleted_by_report;
    }

    /**
     * レスポンスの制限理由を取得
     * 承認・未承認問わず、全ての通報理由を取得
     * 
     * @return array
     */
    public function getRestrictionReasons(): array
    {
        $reasons = $this->report_restriction_reasons;

        return is_array($reasons) ? array_values($reasons) : [];
    }

    /**
     * 通報・お知らせ用：リプライ本文のプレーン文字列。本文が空のときはメディアのみ投稿として種別（画像／動画／音声）を返す。
     */
    public function plainBodyOrMediaKindForNotifications(): string
    {
        $plain = trim(strip_tags((string) ($this->body ?? '')));
        if ($plain !== '') {
            return $plain;
        }

        return match ($this->media_type ?? '') {
            'image' => '画像',
            'video' => '動画',
            'audio' => '音声',
            default => !empty($this->media_file) ? 'メディア' : '',
        };
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
            try {
                $thread = $response->thread ?? Thread::withTrashed()->find($response->thread_id);
                if ($thread) {
                    $thread->updateResponsesCountUp();
                }
            } catch (\Throwable $e) {
                \Log::warning('Response created: updateResponsesCountUp failed', [
                    'response_id' => $response->response_id ?? null,
                    'thread_id' => $response->thread_id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        // 削除ログは deleting(削除前) に記録する
        // deleted(削除後) だと response_change_logs のFK制約により挿入が失敗する
        static::deleting(function ($response) {
            try {
                ResponseChangeLog::logDelete($response, 'レスポンスが削除されました');
            } catch (\Throwable $e) {
                \Log::warning('ResponseChangeLog::logDelete failed', [
                    'response_id' => $response->response_id ?? null,
                    'thread_id' => $response->thread_id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        });

        // レスポンスが削除された時にスレッドのレスポンス数を更新
        static::deleted(function ($response) {
            // 通報等でスレッドが先にソフトデリートされている場合、
            // 通常のリレーションだと thread が null になるため withTrashed で取得する
            try {
                $thread = $response->thread ?? Thread::withTrashed()->find($response->thread_id);
                if ($thread) {
                    $thread->updateResponsesCountDown();
                }
            } catch (\Throwable $e) {
                \Log::warning('Response deleted: updateResponsesCountDown failed', [
                    'response_id' => $response->response_id ?? null,
                    'thread_id' => $response->thread_id ?? null,
                    'error' => $e->getMessage(),
                ]);
            }
        });
    }
}
