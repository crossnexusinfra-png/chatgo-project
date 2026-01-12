<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Cache;
use Illuminate\Database\Eloquent\SoftDeletes;

class Thread extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * 主キーのカラム名を指定
     */
    protected $primaryKey = 'thread_id';

    /**
     * フォームからの入力を許可するカラムを指定します。
     *
     * @var array
     */
    protected $fillable = [
        'title',
        'tag',
        'user_id',
        'responses_count',
        'access_count',
        'is_r18',
        'image_path',
        'parent_thread_id',
        'continuation_thread_id',
    ];

    /**
     * このスレッドを作成したユーザーを取得します。
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    /**
     * このスレッドに属するレスポンスを取得します。
     */
    public function responses()
    {
        return $this->hasMany(Response::class, 'thread_id', 'thread_id');
    }

    /**
     * このスレッドのアクセス記録を取得します。
     */
    public function accesses()
    {
        return $this->hasMany(ThreadAccess::class, 'thread_id', 'thread_id');
    }

    /**
     * このスレッドの通報を取得します。
     */
    public function reports()
    {
        return $this->hasMany(Report::class, 'thread_id', 'thread_id');
    }

    /**
     * このスレッドをお気に入りしたレコード
     */
    public function favorites()
    {
        return $this->hasMany(ThreadFavorite::class, 'thread_id', 'thread_id');
    }

    /**
     * このスレッドをお気に入りしているユーザー
     */
    public function favoredByUsers()
    {
        return $this->belongsToMany(User::class, 'thread_favorites', 'thread_id', 'user_id', 'thread_id', 'user_id');
    }

    /**
     * スレッドが制限されているかどうかを判定
     * 投稿制限が必要な場合はtrueを返す
     * 
     * @return bool
     */
    public function isRestricted(): bool
    {
        // 特定理由によるスコア合計が1以上
        $restrictedScore = \App\Models\Report::calculateThreadRestrictedReasonScore($this->thread_id);
        if ($restrictedScore >= 1.0) {
            return true;
        }
        
        // 「異なる思想」のスコア合計が3以上
        $ideologyScore = \App\Models\Report::calculateThreadIdeologyReportScore($this->thread_id);
        if ($ideologyScore >= 3.0) {
            return true;
        }
        
        // 「成人向けコンテンツが含まれる」のスコア合計が2以上
        $adultContentScore = \App\Models\Report::calculateThreadAdultContentReportScore($this->thread_id);
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
        return \App\Models\Report::where('thread_id', $this->thread_id)
            ->where('is_approved', true)
            ->exists();
    }

    /**
     * スレッドの制限理由を取得
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
            'コンテンツ規制違反',
            'その他'
        ];
        
        // 特定理由で半年以内の通報を取得（拒否された通報は除外、承認済みまたは未処理のみ）
        $reports = $this->reports()
            ->whereIn('reason', $restrictedReasons)
            ->where('created_at', '>=', $sixMonthsAgo)
            ->where(function($q) {
                $q->where('is_approved', true)
                  ->orWhereNull('approved_at');
            })
            ->get();
        
        foreach ($reports as $report) {
            if (!in_array($report->reason, $reasons)) {
                $reasons[] = $report->reason;
            }
        }
        
        // 「異なる思想」のスコア合計が3以上の場合も追加
        $ideologyScore = \App\Models\Report::calculateThreadIdeologyReportScore($this->thread_id);
        if ($ideologyScore >= 3.0) {
            $reasons[] = '異なる思想に関しての意見の押し付け、妨害';
        }
        
        // 「成人向けコンテンツが含まれる」のスコア合計が2以上の場合も追加
        $adultContentScore = \App\Models\Report::calculateThreadAdultContentReportScore($this->thread_id);
        if ($adultContentScore >= 2.0) {
            $reasons[] = '成人向けコンテンツが含まれる';
        }
        
        return array_unique($reasons);
    }

    /**
     * レスポンス数を更新(+1)します。
     */
    public function updateResponsesCountUp()
    {
        $this->increment('responses_count');
        
        // キャッシュをクリア
        Cache::forget('threads_most_responses');
    }

    /**
     * レスポンス数を更新(-1)します。
     */
    public function updateResponsesCountDown()
    {
        $this->decrement('responses_count');
        
        // キャッシュをクリア
        Cache::forget('threads_most_responses');
    }

    /**
     * アクセス数を更新(+1)します。
     */
    public function updateAccessCountUp()
    {
        $this->increment('access_count');
        
        // キャッシュをクリア
        Cache::forget('threads_popular');
    }

    /**
     * 話題のスレッドを取得するスコープ
     */
    public function scopePopular(Builder $query, int $limit = 5)
    {
        return $query->orderBy('access_count', 'desc')->take($limit);
    }

    /**
     * 最新のスレッドを取得するスコープ
     */
    public function scopeLatestThreads(Builder $query, int $limit = 5)
    {
        return $query->orderBy('created_at', 'desc')->take($limit);
    }

    /**
     * レスポンス数の多いスレッドを取得するスコープ
     */
    public function scopeMostResponses(Builder $query, int $limit = 5)
    {
        return $query->orderBy('responses_count', 'desc')->take($limit);
    }

    /**
     * タグでフィルタリングするスコープ
     */
    public function scopeByTag(Builder $query, string $tag)
    {
        return $query->where('tag', $tag);
    }

    /**
     * タイトルで検索するスコープ
     * - 2文字以上で検索を有効化
     * - 空白（全角or半角）でAND検索
     * - -(半角)で除外検索（文頭または空白直後）
     */
    public function scopeSearch(Builder $query, string $searchTerm)
    {
        // 検索クエリを解析（AND検索と除外検索）
        $keywords = $this->parseSearchQuery($searchTerm);
        
        // 有効なキーワードが2文字未満の場合、検索しない
        $validKeywords = array_filter($keywords['include'], function($keyword) {
            return mb_strlen($keyword) >= 2;
        });
        
        if (empty($validKeywords)) {
            return $query->whereRaw('1 = 0'); // 何も返さない
        }
        
        return $query->where(function($q) use ($validKeywords, $keywords) {
            // AND検索（含むべきキーワード）
            foreach ($validKeywords as $keyword) {
                $q->where('title', 'like', '%' . $keyword . '%');
            }
            
            // 除外検索（除外すべきキーワード）
            foreach ($keywords['exclude'] as $excludeKeyword) {
                if (mb_strlen($excludeKeyword) >= 2) {
                    $q->where('title', 'not like', '%' . $excludeKeyword . '%');
                }
            }
        });
    }
    
    /**
     * 検索クエリを解析してキーワードと除外キーワードに分ける
     * @param string $searchTerm
     * @return array
     */
    private function parseSearchQuery(string $searchTerm): array
    {
        $result = [
            'include' => [],
            'exclude' => []
        ];
        
        // 全角空白と半角空白を統一的に処理
        $searchTerm = str_replace('　', ' ', $searchTerm);
        
        // キーワードを分割
        $parts = explode(' ', $searchTerm);
        
        foreach ($parts as $part) {
            $part = trim($part);
            if (empty($part)) {
                continue;
            }
            
            // 除外キーワード（-で始まる、または空白の後に-がある）
            if (mb_substr($part, 0, 1) === '-') {
                $excludeWord = mb_substr($part, 1);
                if (!empty($excludeWord)) {
                    $result['exclude'][] = $excludeWord;
                }
            } else {
                $result['include'][] = $part;
            }
        }
        
        return $result;
    }

    /**
     * タグと検索ワードの両方でフィルタリングするスコープ
     */
    public function scopeByTagAndSearch(Builder $query, string $tag, string $searchTerm)
    {
        // 検索クエリを解析（AND検索と除外検索）
        $keywords = $this->parseSearchQuery($searchTerm);
        
        // 有効なキーワードが2文字未満の場合、検索しない
        $validKeywords = array_filter($keywords['include'], function($keyword) {
            return mb_strlen($keyword) >= 2;
        });
        
        if (empty($validKeywords)) {
            return $query->whereRaw('1 = 0'); // 何も返さない
        }
        
        return $query->where('tag', $tag)
                    ->where(function($q) use ($validKeywords, $keywords) {
                        // AND検索（含むべきキーワード）
                        foreach ($validKeywords as $keyword) {
                            $q->where('title', 'like', '%' . $keyword . '%');
                        }
                        
                        // 除外検索（除外すべきキーワード）
                        foreach ($keywords['exclude'] as $excludeKeyword) {
                            if (mb_strlen($excludeKeyword) >= 2) {
                                $q->where('title', 'not like', '%' . $excludeKeyword . '%');
                            }
                        }
                    });
    }

    /**
     * キャッシュ付きの人気スレッドを取得
     */
    public static function getPopularThreads(int $limit = 5)
    {
        return Cache::remember('threads_popular', 300, function () use ($limit) {
            return static::popular($limit)->get();
        });
    }

    /**
     * キャッシュ付きの最新スレッドを取得
     */
    public static function getLatestThreads(int $limit = 5)
    {
        return Cache::remember('threads_latest', 300, function () use ($limit) {
            return static::latestThreads($limit)->get();
        });
    }

    /**
     * キャッシュ付きのレスポンス数が多いスレッドを取得
     */
    public static function getMostResponsesThreads(int $limit = 5)
    {
        return Cache::remember('threads_most_responses', 300, function () use ($limit) {
            return static::mostResponses($limit)->get();
        });
    }

    /**
     * 今月注目のスレッドを取得するスコープ
     */
    public function scopeTrendingThisMonth(Builder $query, int $limit = 5)
    {
        $startDate = now()->subDays(30);
        
        return $query->withCount([
            'accesses as recent_access_count' => function($q) use ($startDate) {
                $q->where('accessed_at', '>=', $startDate);
            }
        ])->orderBy('recent_access_count', 'desc')->take($limit);
    }

    /**
     * キャッシュ付きの今月注目のスレッドを取得
     */
    public static function getTrendingThreads(int $limit = 5)
    {
        return Cache::remember('threads_trending', 300, function () use ($limit) {
            return static::trendingThisMonth($limit)->get();
        });
    }

    /**
     * 期間を指定して閲覧数を集計する
     */
    public function getAccessCountInPeriod($days = null)
    {
        $query = $this->accesses();
        
        if ($days !== null) {
            $query->where('accessed_at', '>=', now()->subDays($days));
        }
        
        return $query->count();
    }

    /**
     * 完了済みスレッドをフィルタリングするスコープ
     * レスポンス数が1000以上の場合、完結済みとみなす
     */
    public function scopeFilterByCompletion(Builder $query, $status = 'all')
    {
        switch ($status) {
            case 'completed':
                return $query->where('responses_count', '>=', 1000);
            case 'incomplete':
                return $query->where('responses_count', '<', 1000);
            case 'all':
            default:
                return $query;
        }
    }

    /**
     * 期間を指定して閲覧数順にソートするスコープ
     */
    public function scopeOrderByAccessCountInPeriod(Builder $query, $days = null)
    {
        if ($days === null) {
            // 全期間のアクセス数でソート
            return $query->orderBy('access_count', 'desc');
        }
        
        // 指定期間内のアクセス数を計算してソート
        $startDate = now()->subDays($days);
        
        // withCountでrecent_access_countを追加
        // 注意: 既にrecent_access_countが追加されているクエリに対しては
        // 重複が発生する可能性があるため、呼び出し側で注意が必要
        $query->withCount([
            'accesses as recent_access_count' => function($q) use ($startDate) {
                $q->where('accessed_at', '>=', $startDate);
            }
        ]);
        
        return $query->orderBy('recent_access_count', 'desc');
    }

    /**
     * 18歳未満のユーザーに対してR18スレッドを除外するスコープ
     * 
     * @param Builder $query
     * @param bool $isAdult ユーザーが18歳以上かどうか
     * @return Builder
     */
    public function scopeFilterR18Threads(Builder $query, bool $isAdult = false)
    {
        if (!$isAdult) {
            // R18タグ（3種類）を定義
            $r18Tags = [
                '成人向けメディア・コンテンツ・創作',
                '性体験談・性的嗜好・フェティシズム',
                'アダルト業界・風俗・ナイトワーク'
            ];
            
            // 18歳未満の場合はR18スレッド（is_r18=true）またはR18タグのスレッドを除外
            // つまり、is_r18=false かつ R18タグではないスレッドのみ表示
            return $query->where('is_r18', false)
                         ->whereNotIn('tag', $r18Tags);
        }
        
        // 18歳以上の場合はすべて表示
        return $query;
    }

    /**
     * R18スレッドかどうかを判定
     * 
     * @return bool
     */
    public function isR18(): bool
    {
        return $this->is_r18 === true;
    }

    /**
     * このスレッドの続きスレッド要望を取得します。
     */
    public function continuationRequests()
    {
        return $this->hasMany(ThreadContinuationRequest::class, 'thread_id', 'thread_id');
    }

    /**
     * このスレッドの親スレッドを取得します。
     */
    public function parentThread()
    {
        return $this->belongsTo(Thread::class, 'parent_thread_id', 'thread_id');
    }

    /**
     * このスレッドの続きスレッドを取得します。
     */
    public function continuationThread()
    {
        return $this->belongsTo(Thread::class, 'continuation_thread_id', 'thread_id');
    }

    /**
     * レスポンス数が上限に達しているかどうかを判定
     * 
     * @return bool
     */
    public function isResponseLimitReached(): bool
    {
        $maxResponses = config('performance.thread.max_responses', 60);
        return $this->responses()->count() >= $maxResponses;
    }

    /**
     * 続きスレッドの要望数を取得（スレッド主を除く）
     * 
     * @return int
     */
    public function getContinuationRequestCount(): int
    {
        // スレッド主のユーザーIDを取得
        if (!$this->user_id) {
            return $this->continuationRequests()->count();
        }
        
        // スレッド主を除外した要望数を取得
        return $this->continuationRequests()
            ->where('user_id', '!=', $this->user_id)
            ->count();
    }

    /**
     * 指定ユーザーが続きスレッドを要望しているかどうか
     * 
     * @param int $userId
     * @return bool
     */
    public function hasContinuationRequestFromUser(int $userId): bool
    {
        return $this->continuationRequests()->where('user_id', $userId)->exists();
    }

    /**
     * 続きスレッドを作成すべきかどうかを判定
     * スレッド主以外の3ユーザー以上の要望 + スレッド主の要望があればtrue
     * 
     * @return bool
     */
    public function shouldCreateContinuation(): bool
    {
        $threshold = config('performance.thread.continuation_request_threshold', 3);
        $requestCount = $this->getContinuationRequestCount(); // 既にスレッド主を除外した数
        
        if ($requestCount < $threshold) {
            return false;
        }

        // スレッド主の要望を確認
        if (!$this->user_id) {
            return false;
        }

        $hasOwnerRequest = $this->hasContinuationRequestFromUser($this->user_id);
        
        return $hasOwnerRequest;
    }

    /**
     * このスレッドの番号を取得
     * 親スレッドは#0（続きスレッドが存在する場合のみ）、続きスレッドは#1, #2, ...と連番
     * 親スレッドから連鎖的に辿って何番目の続きスレッドかを計算
     * 
     * @return int|null スレッドの番号（親スレッドで続きスレッドがない場合はnull、続きスレッドがある場合は0、続きスレッドは1以上）
     */
    public function getContinuationNumber(): ?int
    {
        if (!$this->parent_thread_id) {
            // 親スレッドの場合、続きスレッドが存在するかチェック
            if ($this->continuation_thread_id) {
                return 0; // 続きスレッドが存在する親スレッドの場合は0
            }
            return null; // 続きスレッドがない親スレッドの場合はnull
        }

        // 親スレッドから連鎖的に辿って番号を計算
        $number = 1;
        $currentThread = $this->parentThread;
        
        while ($currentThread && $currentThread->parent_thread_id) {
            $number++;
            $currentThread = $currentThread->parentThread;
        }
        
        return $number;
    }

    /**
     * タイトルから「(続き)」を削除したタイトルを取得
     * 
     * @return string
     */
    public function getCleanTitle(): string
    {
        return preg_replace('/\s*\(続き\)\s*$/', '', $this->title);
    }
}
