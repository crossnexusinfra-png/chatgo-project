<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Facades\DB;

class ThreadAccess extends Model
{
    use HasFactory;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'access_id';

    /**
     * タイムスタンプを無効化
     */
    public $timestamps = false;

    /**
     * フォームからの入力を許可するカラムを指定します。
     *
     * @var array
     */
    protected $fillable = [
        'user_name',
        'user_id',
        'thread_id',
        'accessed_at',
    ];

    /**
     * このアクセス記録が属するスレッドを取得します。
     */
    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'thread_id');
    }

    /**
     * このアクセス記録が属するユーザーを取得します。
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * ユーザーの閲覧履歴から閲覧回数の多いタグを取得
     * @param string|null $userName ユーザー名（nullの場合は全ユーザー）
     * @param int $limit 取得するタグ数
     * @return array タグとその閲覧回数の配列
     */
    public static function getTopTagsFromHistory($userId = null, $limit = 3)
    {
        // 有効なタグのリストを取得
        $validTags = \App\Services\LanguageService::getValidTags();
        
        $query = static::query()
            ->join('threads', 'thread_accesses.thread_id', '=', 'threads.thread_id')
            ->select('threads.tag', DB::raw('COUNT(*) as view_count'))
            ->whereIn('threads.tag', $validTags) // 有効なタグのみを取得
            ->groupBy('threads.tag')
            ->orderBy('view_count', 'desc');
        
        // ログイン時はそのユーザーの閲覧履歴から取得
        // 非ログイン時は全ユーザーの閲覧履歴から取得
        if ($userId) {
            // ログイン時：user_idでフィルタリング
            $query->where('thread_accesses.user_id', $userId);
        }
        // 非ログイン時：フィルタリングなし（全ユーザーの閲覧履歴から取得）
        
        $results = $query->limit($limit)->get();
        
        return $results->map(function($item) {
            return [
                'tag' => $item->tag,
                'view_count' => $item->view_count
            ];
        })->toArray();
    }
}
