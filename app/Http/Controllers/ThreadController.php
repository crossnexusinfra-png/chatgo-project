<?php

namespace App\Http\Controllers;

use App\Models\Thread; // Threadモデルを使用するためにインポート
use Illuminate\Http\Request; // Requestクラスを使用するためにインポート
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Intervention\Image\Laravel\Facades\Image;
use App\Models\ThreadFavorite;
use App\Models\ResidenceHistory;
use App\Services\MediaFileValidationService;
use App\Services\SpamDetectionService;
use App\Services\SafeBrowsingService;
use App\Models\Report;
use App\Models\AdminMessage;

/**
 * 掲示板のスレッドに関連するリクエストを処理するコントローラー
 */
class ThreadController extends Controller
{
    /**
     * スレッドの一覧を表示する
     *
     * @return \Illuminate\View\View
     */
    public function index()
    {
        // ユーザーが18歳以上かどうかを判定
        // 非ログイン時はフィルタリングしない（R18スレッドも表示）
        $isLoggedIn = auth()->check();
        $isAdult = $isLoggedIn && auth()->user() ? auth()->user()->isAdult() : false;
        
        // キャッシュは使用せず、直接データを取得（パフォーマンス問題を回避）
        // メインページでは各カテゴリ5件まで表示
        
        // popular: 実際の総数を取得してから、5件を取得
        // 未成年ログイン時のみR18スレッドをフィルタリング
        $popularBaseQuery = $isLoggedIn && !$isAdult ? Thread::filterR18Threads($isAdult) : Thread::query();
        $popularTotalCount = (clone $popularBaseQuery)->count();
        $popularThreads = (clone $popularBaseQuery)->with('user')->orderBy('access_count', 'desc')->take(5)->get();
        
        // trending: 実際の総数を取得してから、5件を取得
        $trendingBaseQuery = $isLoggedIn && !$isAdult ? Thread::filterR18Threads($isAdult) : Thread::query();
        $startDate = now()->subDays(30);
        $trendingCountQuery = (clone $trendingBaseQuery)->withCount([
            'accesses as recent_access_count' => function($q) use ($startDate) {
                $q->where('accessed_at', '>=', $startDate);
            }
        ]);
        $trendingTotalCount = $trendingCountQuery->count();
        $trendingThreads = (clone $trendingBaseQuery)->withCount([
            'accesses as recent_access_count' => function($q) use ($startDate) {
                $q->where('accessed_at', '>=', $startDate);
            }
        ])->orderBy('recent_access_count', 'desc')->take(5)->get();
        
        // latest: 実際の総数を取得してから、5件を取得
        $latestBaseQuery = $isLoggedIn && !$isAdult ? Thread::filterR18Threads($isAdult) : Thread::query();
        $latestTotalCount = (clone $latestBaseQuery)->count();
        $latestThreads = (clone $latestBaseQuery)->with('user')->orderBy('created_at', 'desc')->take(5)->get();
        
        // ユーザーの閲覧履歴から閲覧回数の多いタグを取得（ログイン時はユーザーの閲覧履歴、非ログイン時は全ユーザーの閲覧履歴から取得）
        $userId = auth()->check() && auth()->user() ? auth()->user()->user_id : null;
        $topTags = \App\Models\ThreadAccess::getTopTagsFromHistory($userId, 3);
        
        // 有効なタグのリストを取得
        $validTags = \App\Services\LanguageService::getValidTags();
        
        // 各タグに対応するスレッドを取得（1カ月閲覧回数順、5件まで）
        $tagThreads = [];
        foreach ($topTags as $tagInfo) {
            $tagName = $tagInfo['tag'];
            
            // 有効なタグのみを処理
            if (!in_array($tagName, $validTags)) {
                continue;
            }
            
            // タグの総数を取得（take()を適用する前）
            $tagBaseQuery = Thread::byTag($tagName);
            if ($isLoggedIn && !$isAdult) {
                $tagBaseQuery = $tagBaseQuery->filterR18Threads($isAdult);
            }
            $tagTotalCount = (clone $tagBaseQuery)->count();
            
            // スレッドを取得（1カ月閲覧回数順、5件まで）
            $threads = (clone $tagBaseQuery)
                ->with('user')
                ->withCount([
                    'accesses as recent_access_count' => function($q) {
                        $q->where('accessed_at', '>=', now()->subDays(30));
                    }
                ])
                ->orderBy('recent_access_count', 'desc')
                ->take(5)
                ->get();
            
            if ($threads->isNotEmpty()) {
                $tagThreads[$tagName] = [
                    'threads' => $threads,
                    'total_count' => $tagTotalCount,
                ];
            }
        }


        // ログイン時: お気に入りのスレッド（新しい順、最大5件に制限）
        $favoriteThreads = collect();
        // ログイン時: 最近アクセスしたスレッド（直近アクセスの新しい順、最大5件に制限）
        $recentAccessThreads = collect();
        if (auth()->check()) {
            $uid = auth()->user()->user_id;

            // お気に入り（最大5件に制限）- JOINを使用してパフォーマンス向上
            $favoriteThreadsQuery = Thread::query();
            if ($isLoggedIn && !$isAdult) {
                $favoriteThreadsQuery = $favoriteThreadsQuery->filterR18Threads($isAdult);
            }
            $favoriteThreads = $favoriteThreadsQuery
                ->with('user')
                ->join('thread_favorites', function($join) use ($uid) {
                    $join->on('threads.thread_id', '=', 'thread_favorites.thread_id')
                         ->where('thread_favorites.user_id', '=', $uid);
                })
                ->select('threads.*')
                ->orderByDesc('thread_favorites.created_at')
                ->take(5)
                ->get();

            // 最近アクセス（最大5件に制限）- JOINを使用してパフォーマンス向上
            $recentAccessThreadsQuery = Thread::query();
            if ($isLoggedIn && !$isAdult) {
                $recentAccessThreadsQuery = $recentAccessThreadsQuery->filterR18Threads($isAdult);
            }
            $recentAccessThreads = $recentAccessThreadsQuery
                ->with('user')
                ->join('thread_accesses', function($join) use ($uid) {
                    $join->on('threads.thread_id', '=', 'thread_accesses.thread_id')
                         ->where('thread_accesses.user_id', '=', $uid);
                })
                ->select('threads.*', \DB::raw('MAX(thread_accesses.accessed_at) as max_accessed_at'))
                ->groupBy('threads.thread_id')
                ->orderByDesc('max_accessed_at')
                ->take(5)
                ->get();
        }

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        // 作成したスレッド欄は削除済み（互換性のためnullを設定）
        $myThreads = null;
        $myThreadsTotalCount = 0;

        // スレッドの制限情報を一括取得（N+1問題を回避）
        // ただし、getThreadRestrictionData()は一時的に無効化されているため、空の配列を返す
        $allThreads = collect()
            ->merge($popularThreads)
            ->merge($trendingThreads)
            ->merge($latestThreads)
            ->merge($favoriteThreads)
            ->merge($recentAccessThreads);
        
        foreach ($tagThreads as $tagData) {
            if (isset($tagData['threads'])) {
                $allThreads = $allThreads->merge($tagData['threads']);
            }
        }
        
        $threadRestrictionData = $this->getThreadRestrictionData($allThreads->unique('thread_id'));
        
        // スレッド画像の通報スコアを一括取得（N+1問題を回避）
        $uniqueThreadIds = $allThreads->unique('thread_id')->pluck('thread_id')->toArray();
        $threadImageReportScoreData = [];
        foreach ($uniqueThreadIds as $threadId) {
            $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
            // スレッド画像関連の通報理由で削除された場合は画像も非表示
            $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
            $threadImageReportScoreData[$threadId] = [
                'score' => $score,
                'isBlurred' => $score >= 1.0,
                'isDeletedByImageReport' => $isDeletedByImageReport
            ];
        }

        // データを配列にまとめる（すべての変数を含める）
        return view('threads.index', compact(
            'popularThreads', 'popularTotalCount', 
            'trendingThreads', 'trendingTotalCount', 
            'latestThreads', 'latestTotalCount', 
            'tagThreads', 
            'myThreads', 'myThreadsTotalCount',
            'favoriteThreads', 'recentAccessThreads', 
            'lang', 'threadRestrictionData', 'threadImageReportScoreData'
        ));
    }

    /**
     * お気に入りをトグル
     */
    public function toggleFavorite(Thread $thread)
    {
        if (!auth()->check()) {
            return redirect()->route('auth.choice');
        }

        // IDOR防止: お気に入りに追加する権限をチェック
        Gate::authorize('favorite', $thread);

        $userId = auth()->user()->user_id;
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $existing = ThreadFavorite::where('user_id', $userId)->where('thread_id', $thread->thread_id)->first();
        if ($existing) {
            $existing->delete();
            $message = \App\Services\LanguageService::trans('favorite_removed', $lang);
        } else {
            ThreadFavorite::create([
                'user_id' => $userId,
                'thread_id' => $thread->thread_id,
            ]);
            $message = \App\Services\LanguageService::trans('favorite_added', $lang);
        }

        // 関連キャッシュを短命にしているため明示クリアは省略（必要なら追加）
        return back()->with('success', $message);
    }

    /**
     * スレッドタイトルで検索する
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function search(Request $request)
    {
        $query = $request->get('q');
        
        // フィルタリングパラメータ（デフォルト設定: sort_by=latest, スレッド作成日時順）
        $sortBy = $request->get('sort_by', 'latest');
        $period = $request->get('period');
        $completionStatus = $request->get('completion', 'all');
        
        // ユーザーが18歳以上かどうかを判定
        $isAdult = auth()->check() && auth()->user() ? auth()->user()->isAdult() : false;
        
        // 検索クエリが空または空白のみの場合は検索を実行しない
        if (!$query || trim($query) === '' || mb_strlen(trim($query)) < 2) {
            $threads = collect(); // 空のコレクションを返す
            $searchQuery = $query; // ヘッダーの検索欄に表示するため
            return view('threads.search', compact('threads', 'query', 'sortBy', 'period', 'completionStatus', 'searchQuery'));
        }
        
        // 検索クエリでスレッドを取得
        $threadsQuery = Thread::search($query);
        
        // R18タグのフィルタリング（未成年ログイン時のみ）
        $isLoggedIn = auth()->check();
        if ($isLoggedIn && !$isAdult) {
            $threadsQuery = $threadsQuery->filterR18Threads($isAdult);
        }
        
        // 完結状態でフィルタリング
        $threadsQuery = $threadsQuery->filterByCompletion($completionStatus);
        
        // ソート処理
        if ($sortBy === 'popular') {
            // 閲覧数順
            if ($period === '30' || $period === '365' || $period === 'all') {
                $days = $period === '30' ? 30 : ($period === '365' ? 365 : null);
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod($days);
            } else {
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod(null);
            }
        } else {
            // 新しい順（デフォルト）
            $threadsQuery = $threadsQuery->orderBy('created_at', 'desc');
        }
        
        // 総件数を取得
        $totalCount = $threadsQuery->count();
        
        // 最初は20件のみ取得
        $threads = $threadsQuery->with('user')->take(20)->get();
        $searchQuery = $query; // ヘッダーの検索欄に表示するため

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        // スレッドの制限情報を一括取得（N+1問題を回避）
        $threadRestrictionData = $this->getThreadRestrictionData($threads);
        
        // スレッド画像の通報スコアを一括取得（N+1問題を回避）
        $threadIds = $threads->pluck('thread_id')->toArray();
        $threadImageReportScoreData = [];
        foreach ($threadIds as $threadId) {
            $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
            // スレッド画像関連の通報理由で削除された場合は画像も非表示
            $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
            $threadImageReportScoreData[$threadId] = [
                'score' => $score,
                'isBlurred' => $score >= 1.0,
                'isDeletedByImageReport' => $isDeletedByImageReport
            ];
        }

        return view('threads.search', compact('threads', 'query', 'sortBy', 'period', 'completionStatus', 'searchQuery', 'threadRestrictionData', 'threadImageReportScoreData', 'lang', 'totalCount'));
    }

    /**
     * タグでスレッドを検索する
     *
     * @param  string  $tag
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\View\View
     */
    public function searchByTag($tag, Request $request)
    {
        try {
            // Laravelは自動的にURLパラメータをデコードするため、urldecodeは不要
            // タグ名の前後の空白を削除
            $tag = trim($tag);
        
        $searchQuery = $request->get('q');
        
        // フィルタリングパラメータ（デフォルト設定: sort_by=popular, period=30, 1カ月閲覧回数順）
        $sortBy = $request->get('sort_by', 'popular');
        $period = $request->get('period', '30');
        $completionStatus = $request->get('completion', 'all');
        
        // ユーザーが18歳以上かどうかを判定
        $isAdult = auth()->check() && auth()->user() ? auth()->user()->isAdult() : false;
        
            // デバッグ用ログ（直接ファイルに書き込む方法で確実に記録）
            $this->writeLogDirectly('Tag search requested', [
            'tag' => $tag, 
            'tag_encoded' => urlencode($tag),
            'search_query' => $searchQuery, 
                'is_adult' => $isAdult,
                'timestamp' => now()->setTimezone('Asia/Tokyo')->toDateTimeString()
        ]);
        
        // R18タグ（3種類）を定義
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        
        // R18タグで検索する場合の処理
        $isR18Tag = in_array($tag, $r18Tags);
        
        // デバッグ: データベースに実際に存在するR18タグのスレッド数を確認
        $r18ThreadCount = Thread::whereIn('tag', $r18Tags)->count();
            $this->writeLogDirectly('R18 tag check', [
            'tag_param' => $tag,
            'is_r18_tag' => $isR18Tag,
            'r18_thread_count_in_db' => $r18ThreadCount,
                'is_adult' => $isAdult,
                'timestamp' => now()->setTimezone('Asia/Tokyo')->toDateTimeString()
        ]);
            
            // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
            $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        if ($isR18Tag && !$isAdult) {
            // 18歳未満のユーザーがR18タグで検索しようとした場合、空の結果を返す
            $threads = collect();
                $threadRestrictionData = [];
                return view('threads.tag', compact('threads', 'tag', 'searchQuery', 'sortBy', 'period', 'completionStatus', 'threadRestrictionData', 'lang'))->with('selectedTag', $tag);
        }
        
        // スレッドを取得
        if ($searchQuery) {
            // タグと検索ワードのAND検索
            $threadsQuery = Thread::byTagAndSearch($tag, $searchQuery);
        } else {
            // タグのみでの検索
            $threadsQuery = Thread::byTag($tag);
        }
        
        // デバッグ: byTag後のクエリを確認
        $beforeFilterCount = $threadsQuery->count();
            $this->writeLogDirectly('Before filterR18Threads', [
            'tag' => $tag,
            'count_before_filter' => $beforeFilterCount,
                'is_r18_tag' => $isR18Tag,
                'timestamp' => now()->setTimezone('Asia/Tokyo')->toDateTimeString()
        ]);
        
        // メインページと同様に、すべてのタグ検索でfilterR18Threadsを適用する
        // filterR18Threadsは18歳以上の場合何もしないので、R18タグ検索でも安全
        // これにより、メインページと同じロジックで動作する
        $threadsQuery = $threadsQuery->filterR18Threads($isAdult);
        
        $afterFilterCount = $threadsQuery->count();
            $this->writeLogDirectly('After filterR18Threads', [
            'tag' => $tag,
            'count_after_filter' => $afterFilterCount,
                'is_adult' => $isAdult,
                'timestamp' => now()->setTimezone('Asia/Tokyo')->toDateTimeString()
        ]);
        
        // 完結状態でフィルタリング
        $threadsQuery = $threadsQuery->filterByCompletion($completionStatus);
        
        // ソート処理
        if ($sortBy === 'popular') {
            // 閲覧数順
            if ($period === '30' || $period === '365' || $period === 'all') {
                $days = $period === '30' ? 30 : ($period === '365' ? 365 : null);
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod($days);
            } else {
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod(null);
            }
        } else {
            // 新しい順（デフォルト）
            $threadsQuery = $threadsQuery->orderBy('created_at', 'desc');
        }
        
        // 実際にデータベースから取得する前に、クエリを確認
        $sql = $threadsQuery->toSql();
        $bindings = $threadsQuery->getBindings();
            $this->writeLogDirectly('Tag search query before execute', [
            'tag' => $tag,
            'is_r18' => $isR18Tag,
            'is_adult' => $isAdult,
            'sql' => $sql,
                'bindings' => $bindings,
                'timestamp' => now()->setTimezone('Asia/Tokyo')->toDateTimeString()
        ]);
        
        // 総件数を取得
        $totalCount = $threadsQuery->count();
        
        // 最初は20件のみ取得
        $threads = $threadsQuery->with('user')->take(20)->get();
        
        // 実際に取得されたスレッドのタグ名を確認
        $actualTags = $threads->pluck('tag')->unique()->values()->toArray();
            
            // データベースに存在するすべてのタグを取得（デバッグ用）
            $allTagsInDb = Thread::distinct()->pluck('tag')->toArray();
            
            // 検索したタグがデータベースに存在するか確認
            $tagExists = in_array($tag, $allTagsInDb);
            
            $this->writeLogDirectly('Tag search result', [
            'tag' => $tag,
                'tag_encoded' => urlencode($tag),
                'tag_exists_in_db' => $tagExists,
            'search_query' => $searchQuery,
            'count' => $threads->count(),
            'actual_tags' => $actualTags,
                'all_thread_tags_sample' => array_slice($allTagsInDb, 0, 20), // 最初の20件のみ
                'timestamp' => now()->setTimezone('Asia/Tokyo')->toDateTimeString()
        ]);

        // スレッドの制限情報を一括取得（N+1問題を回避）
        $threadRestrictionData = $this->getThreadRestrictionData($threads);
        
        // スレッド画像の通報スコアを一括取得（N+1問題を回避）
        $threadIds = $threads->pluck('thread_id')->toArray();
        $threadImageReportScoreData = [];
        foreach ($threadIds as $threadId) {
            $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
            // スレッド画像関連の通報理由で削除された場合は画像も非表示
            $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
            $threadImageReportScoreData[$threadId] = [
                'score' => $score,
                'isBlurred' => $score >= 1.0,
                'isDeletedByImageReport' => $isDeletedByImageReport
            ];
        }

        return view('threads.tag', compact('threads', 'tag', 'searchQuery', 'sortBy', 'period', 'completionStatus', 'threadRestrictionData', 'threadImageReportScoreData', 'lang', 'totalCount'))->with('selectedTag', $tag);
            
        } catch (\Exception $e) {
            // エラーをログに記録（直接ファイルに書き込む方法で確実に記録）
            $this->writeLogDirectly('Tag search error', [
                'tag' => $tag ?? 'unknown',
                'error_message' => $e->getMessage(),
                'error_file' => $e->getFile(),
                'error_line' => $e->getLine(),
                'error_trace' => $e->getTraceAsString(),
                'request_url' => $request->fullUrl(),
                'request_method' => $request->method(),
                'timestamp' => now()->setTimezone('Asia/Tokyo')->toDateTimeString()
            ], 'ERROR');
            
            // エラーを再スロー（Laravelのエラーハンドリングに任せる）
            throw $e;
        }
    }

    /**
     * 新しいスレッドをデータベースに保存する
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function store(Request $request)
    {
        // バリデーション
        $request->validate([
            'title' => 'required|max:50',
            'tag' => 'required|max:100',
            'body' => 'required|max:1000',
            'is_r18' => 'nullable|boolean',
            'image' => 'nullable|file',
        ]);

        $lang = \App\Services\LanguageService::getCurrentLanguage();

        // IDOR防止: スレッドを作成する権限をチェック（ログイン必須）
        if (!auth()->check()) {
            return redirect()->route('auth.choice');
        }
        
        // R18スレッドかどうかを判定（Policyでチェックするため）
        $isR18 = $request->has('is_r18') && $request->is_r18 == '1';
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        if (in_array($request->tag, $r18Tags)) {
            $isR18 = true;
        }
        
        // IDOR防止: R18スレッドを作成する権限をチェック（18歳以上のみ）
        $user = auth()->user();
        $policy = new \App\Policies\ThreadPolicy();
        if (!$policy->create($user, $request->tag, $isR18)) {
            return back()->withErrors(['tag' => \App\Services\LanguageService::trans('r18_thread_adult_only', $lang)])
                ->withInput();
        }

        // 画像アップロードの処理
        $imagePath = null;
        if ($request->hasFile('image')) {
            \Log::info('ThreadController: Image file detected (store)', [
                'filename' => $request->file('image')->getClientOriginalName(),
                'mime_type' => $request->file('image')->getMimeType(),
                'size' => $request->file('image')->getSize(),
            ]);
            
            $validationService = new MediaFileValidationService($lang);
            $validationResult = $validationService->validateFile($request->file('image'));
            
            \Log::info('ThreadController: Image file validation result (store)', [
                'valid' => $validationResult['valid'],
                'error' => $validationResult['error'] ?? null,
                'media_type' => $validationResult['media_type'] ?? null,
            ]);
            
            if (!$validationResult['valid']) {
                \Log::warning('ThreadController: File validation failed (store)', [
                    'error' => $validationResult['error'],
                ]);
                return back()->withInput()->withErrors(['image' => $validationResult['error']]);
            }
            
            // 画像のみ許可（動画や音声は不可）
            if ($validationResult['media_type'] !== 'image') {
                $errorMsg = $this->isJapanese($lang)
                    ? 'スレッド画像には画像ファイルのみ使用できます。'
                    : 'Only image files can be used for thread images.';
                return back()->withInput()->withErrors(['image' => $errorMsg]);
            }
            
            // ファイルを保存
            $file = $request->file('image');
            // ユーザー入力のファイル名を直接使わず、hashでリネーム
            $hashedFilename = hash('sha256', time() . $file->getClientOriginalName());
            // MIMEタイプから拡張子を取得（ユーザー入力に依存しない）
            $extension = $this->getExtensionFromMimeType($file->getMimeType(), $validationResult['media_type']);
            $filename = $hashedFilename . '.' . $extension;
            $path = $file->storeAs('thread_images', $filename, 'public');
            $imagePath = $path;
            
            // ファイルが実際に保存されているか確認（Storageファサードを使用してS3対応）
            $disk = \Illuminate\Support\Facades\Storage::disk('public');
            $fileExists = $disk->exists($path);
            $fileSize = $fileExists ? $disk->size($path) : 0;
            
            \Log::info('ThreadController: Image uploaded successfully (store)', [
                'path' => $path,
                'file_exists' => $fileExists,
                'file_size' => $fileSize,
                'storage_url' => Storage::disk('public')->url($path),
            ]);

            // 画像ファイルの再エンコード
            if ($fileExists) {
                $processingService = new \App\Services\MediaFileProcessingService();
                $processingResult = $processingService->reencodeImage($path, 'image', 'public');
                
                if (!$processingResult['success']) {
                    \Log::warning('ThreadController: Image re-encoding failed (store)', [
                        'error' => $processingResult['error'],
                    ]);
                    // 処理に失敗しても続行（ログに記録のみ）
                } else {
                    $newFileSize = $disk->exists($path) ? $disk->size($path) : 0;
                    \Log::info('ThreadController: Image re-encoded successfully (store)', [
                        'new_size' => $newFileSize,
                    ]);
                }
            }
        }

        // スレッド作成数の上限チェック（1日2つまで、標準時の0時リセット）
        if (auth()->check()) {
            $userId = auth()->user()->user_id;
            $todayStart = now()->startOfDay();
            $todayEnd = now()->endOfDay();
            
            $todayThreadCount = Thread::where('user_id', $userId)
                ->whereBetween('created_at', [$todayStart, $todayEnd])
                ->count();
            
            if ($todayThreadCount >= 2) {
                return back()->withErrors(['title' => \App\Services\LanguageService::trans('thread_creation_limit_exceeded', $lang)])
                    ->withInput();
            }
        }

        // R18タグ（3種類）を定義（既に上で定義済み）
        // R18スレッドにするかどうかを判定（既に上で判定済み）
        // Policyでチェック済みのため、ここでは重複チェックを削除

        // URLの安全性チェック（bodyが存在する場合のみ）
        $body = $request->body ?? '';
        \Log::info('ThreadController: Starting URL safety check (store)', [
            'body_length' => strlen($body)
        ]);
        
        $safeBrowsingService = new SafeBrowsingService();
        $urls = $safeBrowsingService->extractUrls($body);
        
        if (!empty($urls)) {
            \Log::info('ThreadController: URLs found in thread body (store)', [
                'url_count' => count($urls),
                'urls' => $urls
            ]);
            
            foreach ($urls as $url) {
                $checkResult = $safeBrowsingService->checkUrl($url);
                
                \Log::info('ThreadController: URL check result (store)', [
                    'url' => $url,
                    'safe' => $checkResult['safe'],
                    'error' => $checkResult['error'] ?? null,
                    'threats' => $checkResult['threats'] ?? []
                ]);
                
                if (!$checkResult['safe']) {
                    // API利用制限エラーの場合
                    if ($checkResult['error'] === 'rate_limit_exceeded') {
                        \Log::warning('ThreadController: Rate limit exceeded, blocking thread creation (store)');
                        return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('url_check_rate_limit', $lang)]);
                    }
                    
                    // 危険なURLまたはAPIエラーの場合
                    \Log::warning('ThreadController: Unsafe URL or API error detected, blocking thread creation (store)', [
                        'url' => $url,
                        'error' => $checkResult['error'] ?? null,
                        'threats' => $checkResult['threats'] ?? []
                    ]);
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('url_check_unsafe', $lang)]);
                }
            }
        } else {
            \Log::debug('ThreadController: No URLs found in thread body (store)');
        }

        // スパム判定（titleとbodyをチェック）
        $title = $request->title ?? '';
        $userName = auth()->check() ? auth()->user()->username : 'anonymous';
        
        // スレッド名（title）のスパムチェック
        if (!empty($title)) {
            \Log::info('ThreadController: Starting spam check for title (store)', [
                'title_length' => strlen($title),
                'user_name' => $userName,
            ]);
            
            $spamDetectionService = new SpamDetectionService();
            $spamResult = $spamDetectionService->checkSpam($title, $userName, []);
            
            if ($spamResult['is_spam']) {
                \Log::warning('ThreadController: Spam detected in title, blocking thread creation (store)', [
                    'reason' => $spamResult['reason'],
                    'ng_word' => $spamResult['ng_word'] ?? null,
                    'similarity' => $spamResult['similarity'] ?? null,
                ]);
                
                if ($spamResult['reason'] === 'ng_word') {
                    return back()->withInput()->withErrors(['title' => \App\Services\LanguageService::trans('spam_ng_word_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'similarity') {
                    return back()->withInput()->withErrors(['title' => \App\Services\LanguageService::trans('spam_similar_response_detected', $lang)]);
                }
            }
        }
        
        // 1スレ目（body）のスパムチェック
        if (!empty($body)) {
            \Log::info('ThreadController: Starting spam check for body (store)', [
                'body_length' => strlen($body),
                'user_name' => $userName,
                'url_count' => count($urls ?? []),
            ]);
            
            $spamDetectionService = new SpamDetectionService();
            $spamResult = $spamDetectionService->checkSpam($body, $userName, $urls ?? []);
            
            if ($spamResult['is_spam']) {
                \Log::warning('ThreadController: Spam detected in body, blocking thread creation (store)', [
                    'reason' => $spamResult['reason'],
                    'ng_word' => $spamResult['ng_word'] ?? null,
                    'similarity' => $spamResult['similarity'] ?? null,
                    'url' => $spamResult['url'] ?? null,
                    'count' => $spamResult['count'] ?? null,
                ]);
                
                if ($spamResult['reason'] === 'ng_word') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_ng_word_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'similarity') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_similar_response_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'url_similarity') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_similar_url_detected', $lang)]);
                } elseif ($spamResult['reason'] === 'url_post_limit') {
                    return back()->withInput()->withErrors(['body' => \App\Services\LanguageService::trans('spam_url_post_limit_exceeded', $lang)]);
                }
            }
        }

        // コインを消費（スレッド作成に2コイン必要）
        if (auth()->check()) {
            $coinService = new \App\Services\CoinService();
            $cost = $coinService->getThreadCreationCost();
            $user = auth()->user();
            
            if (!$coinService->consumeCoins($user, $cost)) {
                return back()->withErrors(['title' => \App\Services\LanguageService::trans('insufficient_coins', $lang)])
                    ->withInput();
            }
        }

        // ユーザーIDを取得（ログインユーザーのみ許可）
        if (!auth()->check()) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return back()->withErrors(['title' => \App\Services\LanguageService::trans('login_required', $lang)])
                ->withInput();
        }
        
        $userId = auth()->user()->user_id;

        $sendTimeLang = \App\Services\TranslationService::normalizeLang(auth()->user()->language ?? 'EN');

        // スレッドを作成（送信時の表示言語を保存）
        $thread = Thread::create([
            'title' => $request->title,
            'source_lang' => $sendTimeLang,
            'tag' => $request->tag,
            'user_id' => $userId,
            'responses_count' => 1, // 最初のレスポンスを含む
            'is_r18' => $isR18,
            'image_path' => $imagePath,
        ]);

        // 最初のレスポンスを作成（送信時の表示言語を保存）
        $thread->responses()->create([
            'user_id' => $userId,
            'body' => $request->body,
            'source_lang' => $sendTimeLang,
            'responses_num' => 1,
        ]);

        // キャッシュをクリア
        Cache::forget('threads_index_');
        Cache::forget('threads_latest');
        Cache::forget('threads_popular');
        Cache::forget('threads_most_responses');

        return redirect()->route('threads.show', $thread->thread_id)
            ->with('success', \App\Services\LanguageService::trans('thread_created_success', $lang));
    }

    /**
     * 指定されたスレッドを表示する
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function show($id)
    {
        // 削除されたスレッドへのアクセスをチェック
        $thread = Thread::withTrashed()->find($id);
        
        if (!$thread) {
            // スレッドが存在しない場合は404
            abort(404);
        }
        
        if ($thread->trashed()) {
            // 削除されたスレッドにアクセスした場合はカスタムエラーページを表示
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return view('errors.thread-deleted', compact('lang'));
        }
        
        // 最初は最新10件のみ取得（パフォーマンス向上）（翻訳用に parentResponse も取得）
        $initialResponses = $thread->responses()
            ->with(['user', 'parentResponse'])
            ->orderBy('created_at', 'desc')
            ->take(10)
            ->get()
            ->reverse(); // 古い順に表示するため逆順にする
        
        // R18スレッドの場合、了承済みかどうかをチェック
        $isAdult = auth()->check() && auth()->user() ? auth()->user()->isAdult() : false;
        $isAcknowledged = session('acknowledged_thread_' . $thread->thread_id);
        
        // R18タグ（3種類）を定義
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        
        // R18スレッドかどうかを判定（is_r18=true または R18タグ）
        $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
        
        // IDOR防止: R18スレッドの閲覧権限をチェック（18歳未満のユーザーは閲覧不可）
        $currentUser = auth()->user();
        if (!\Illuminate\Support\Facades\Gate::forUser($currentUser)->allows('view', $thread)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return redirect()->route('threads.index')
                ->withErrors(['r18' => \App\Services\LanguageService::trans('r18_thread_adult_only_view', $lang)]);
        }
        
        if ($isR18Thread && !$isAcknowledged) {
            // 非ログイン時または18歳以上のユーザーは注意画面を表示（了承が必要）
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return view('threads.r18-warning', compact('thread', 'lang'));
        }
        
        // アクセス数を記録
        $userId = auth()->check() ? auth()->user()->user_id : null;
        $userName = auth()->check() ? auth()->user()->username : 'anonymous';
        
        $thread->accesses()->create([
            'user_id' => $userId,
            'user_name' => $userName,
            'accessed_at' => now(),
        ]);

        // スレッドのアクセス数を更新
        $thread->updateAccessCountUp();
        
        // ログイン時は閲覧履歴キャッシュをクリア
        if (auth()->check()) {
            $userIdentifier = auth()->id();
            Cache::forget('threads_index_' . $userIdentifier);
        }

        // スレッド作成者とレスポンス投稿者のユーザー情報を取得
        $thread->load('user');
        $userIds = collect([$thread->user_id])
            ->merge($initialResponses->pluck('user_id'))
            ->unique()
            ->filter()
            ->values();

        $users = $this->buildUserMapByUserIds($userIds);
        
        // レスポンスをthreadオブジェクトに設定（ビューで使用するため）
        $thread->responses = $initialResponses;

        // ログインユーザーの情報を取得
        $currentUser = auth()->user();
        $isFavorited = false;
        if ($currentUser) {
            $isFavorited = ThreadFavorite::where('user_id', $currentUser->user_id)
                ->where('thread_id', $thread->thread_id)
                ->exists();
        }

        // ログインユーザーが既に通報しているかどうかを確認
        $userReportedThread = false;
        $userReportedThreadRejected = false;
        $userReportedResponses = collect();
        $userReportedResponseRejected = [];
        
        if ($currentUser) {
            // スレッドの通報状況を確認
            $threadReport = \App\Models\Report::where('user_id', $currentUser->user_id)
                ->where('thread_id', $thread->thread_id)
                ->first();
            
            if ($threadReport) {
                $userReportedThread = true;
                // 拒否された通報かどうか
                $userReportedThreadRejected = $threadReport->approved_at && $threadReport->is_approved === false;
            }
            
            // レスポンスの通報状況を確認（通報済みのレスポンスIDのリストを取得）
            $responseReports = \App\Models\Report::where('user_id', $currentUser->user_id)
                ->whereIn('response_id', $initialResponses->pluck('response_id'))
                ->get();
            
            $reportedResponseIds = [];
            foreach ($responseReports as $report) {
                $reportedResponseIds[] = $report->response_id;
                // 拒否された通報かどうか
                if ($report->approved_at && $report->is_approved === false) {
                    $userReportedResponseRejected[$report->response_id] = true;
                }
            }
            
            $userReportedResponses = collect($reportedResponseIds);
        }

        // スレッドの制限判定（キャッシュを使用して最適化、簡略化）
        $threadCacheKey = 'thread_restriction_' . $thread->thread_id;
        $cachedRestriction = Cache::get($threadCacheKey);
        
        if ($cachedRestriction !== null) {
            $isThreadRestricted = $cachedRestriction['isRestricted'] ?? false;
            $threadRestrictionReasons = $cachedRestriction['restrictionReasons'] ?? [];
            // キャッシュに制限理由が含まれていない場合は取得（R18変更のお知らせ送信に必要）
            if (empty($threadRestrictionReasons) && $isThreadRestricted) {
                $threadRestrictionReasons = $thread->getRestrictionReasons();
            }
        } else {
            // キャッシュがない場合のみ計算
            $isThreadRestricted = $thread->isRestricted();
            $threadRestrictionReasons = $thread->getRestrictionReasons(); // 制限理由を取得（R18変更のお知らせ送信に必要）
            
            // キャッシュに保存
            Cache::put($threadCacheKey, [
                'isRestricted' => $isThreadRestricted,
                'restrictionReasons' => $threadRestrictionReasons,
            ], 300);
        }

        $isThreadDeletedByReport = \App\Models\Report::where('thread_id', $thread->thread_id)->where('is_approved', true)->exists();

        // レスポンスの制限情報を一括取得（N+1問題を回避）
        // レスポンス数が多い場合は制限情報の計算を簡略化してパフォーマンスを向上
        $responseIds = $initialResponses->pluck('response_id')->toArray();
        $responseRestrictionData = [];
        $responseCount = count($responseIds);
        $maxResponsesForFullCheck = 100; // 100件以上は簡略化（パフォーマンス向上）
        
        // レスポンスが存在する場合のみ処理
        if (!empty($responseIds)) {
            // レスポンス数が多い場合は制限情報の計算を完全にスキップ
            if ($responseCount > $maxResponsesForFullCheck) {
                // 簡略化: すべてfalseを返す（制限情報を表示しない）
                foreach ($responseIds as $responseId) {
                    $responseRestrictionData[$responseId] = [
                        'shouldBeHidden' => false,
                        'isDeletedByReport' => false,
                        'restrictionReasons' => [],
                    ];
                }
            } else {
                // 削除されたレスポンスIDを一括取得
                $deletedResponseIds = \App\Models\Report::whereIn('response_id', $responseIds)
                    ->where('is_approved', true)
                    ->pluck('response_id')
                    ->toArray();
                // 通常処理: 制限情報を計算
                $sixMonthsAgo = now()->subMonths(6);
                
                // 通報データを一括取得
                $restrictedReasonList = [
                    'スパム・迷惑行為',
                    '攻撃的・不適切な内容',
                    '不適切なリンク・外部誘導',
                    '成人向け以外のコンテンツ規制違反',
                    'その他'
                ];
                
                $reports = \App\Models\Report::whereIn('response_id', $responseIds)
                    ->where('created_at', '>=', $sixMonthsAgo)
                    ->get()
                    ->groupBy('response_id');
                
                // 通報者のスコアを一括取得してキャッシュ（重複を避ける）
                $uniqueUserIds = $reports->flatten()->pluck('user_id')->unique()->toArray();
                $userReportScores = [];
                foreach ($uniqueUserIds as $userId) {
                    $userReportScores[$userId] = \App\Models\Report::calculateUserReportScore($userId);
                }
                
                foreach ($responseIds as $responseId) {
                    $responseReports = $reports->get($responseId, collect());
                    
                    // 削除判定
                    $isDeleted = in_array($responseId, $deletedResponseIds);
                    
                    // 制限判定
                    $restrictedScore = 0.0;
                    $ideologyScore = 0.0;
                    $adultContentScore = 0.0;
                    $restrictionReasons = [];
                    
                    if ($responseReports->isNotEmpty()) {
                        // 特定理由によるスコア計算
                        $restrictedReports = $responseReports->whereIn('reason', $restrictedReasonList)
                            ->filter(function($report) {
                                return $report->is_approved === true || $report->approved_at === null;
                            });
                        
                        foreach ($restrictedReports as $report) {
                            $restrictedScore += $userReportScores[$report->user_id] ?? 0.3;
                        }
                        
                        // 異なる思想によるスコア計算
                        $ideologyReports = $responseReports->where('reason', '異なる思想に関しての意見の押し付け、妨害')
                            ->filter(function($report) {
                                return $report->is_approved === true || $report->approved_at === null;
                            });
                        
                        foreach ($ideologyReports as $report) {
                            $ideologyScore += $userReportScores[$report->user_id] ?? 0.3;
                        }
                        
                        // 成人向けコンテンツによるスコア計算
                        $adultContentReports = $responseReports->where('reason', '成人向けコンテンツが含まれる')
                            ->filter(function($report) {
                                return $report->is_approved === true || $report->approved_at === null;
                            });
                        
                        foreach ($adultContentReports as $report) {
                            $adultContentScore += $userReportScores[$report->user_id] ?? 0.3;
                        }
                        
                        // 制限理由を取得
                        $restrictedReportsForReasons = $responseReports->whereIn('reason', $restrictedReasonList);
                        foreach ($restrictedReportsForReasons as $report) {
                            if (!in_array($report->reason, $restrictionReasons)) {
                                $restrictionReasons[] = $report->reason;
                            }
                        }
                        
                        // 「異なる思想」のスコアが3以上の場合も制限理由に追加
                        if ($ideologyScore >= 3.0) {
                            $restrictionReasons[] = '異なる思想に関しての意見の押し付け、妨害';
                        }
                        
                        // 「成人向けコンテンツが含まれる」のスコアが2以上の場合も制限理由に追加
                        if ($adultContentScore >= 2.0) {
                            $restrictionReasons[] = '成人向けコンテンツが含まれる';
                        }
                    }
                    
                    $shouldBeHidden = $restrictedScore >= 1.0 || $ideologyScore >= 3.0 || $adultContentScore >= 2.0;
                    
                    $responseRestrictionData[$responseId] = [
                        'shouldBeHidden' => $shouldBeHidden,
                        'isDeletedByReport' => $isDeleted,
                        'restrictionReasons' => $restrictionReasons,
                    ];
                }
            }
        }

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $this->applyTranslationsForThreadShow($thread, $lang, $users);

        // 「成人向けコンテンツが含まれる」で制限がかかった場合、R18変更のお知らせを送信
        $this->sendR18ChangeNotificationIfNeeded($thread, $isThreadRestricted, $threadRestrictionReasons, $responseRestrictionData);
        
        // スレッド画像の通報スコアを計算
        $threadImageReportScore = \App\Models\Report::calculateThreadImageReportScore($thread->thread_id);
        $isThreadImageBlurred = $threadImageReportScore >= 1.0;
        
        // スレッド画像のURLを取得
        $threadImage = $thread->image_path ?: asset('images/default-16x9.svg');
        if ($thread->image_path && strpos($thread->image_path, 'thread_images/') === 0) {
            $threadImageUrl = \Illuminate\Support\Facades\Storage::disk('public')->url($thread->image_path);
        } else {
            $threadImageUrl = $threadImage;
        }
        
        // R18判定
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        $isR18Thread = $thread->is_r18 || in_array($thread->tag, $r18Tags);
        
        // PHP設定を取得してJavaScriptに渡す
        $phpUploadMaxSize = $this->convertToBytes(ini_get('upload_max_filesize'));
        $phpPostMaxSize = $this->convertToBytes(ini_get('post_max_size'));

        // 続きスレッド関連の情報を取得
        $isResponseLimitReached = $thread->isResponseLimitReached();
        $continuationRequestCount = $thread->getContinuationRequestCount();
        $continuationRequestThreshold = config('performance.thread.continuation_request_threshold', 3);
        $isContinuationRequestLimitReached = $continuationRequestCount >= $continuationRequestThreshold;
        $hasUserContinuationRequest = false;
        $hasOwnerContinuationRequest = false;
        $isCurrentUserThreadOwner = false;
        if ($currentUser) {
            $hasUserContinuationRequest = $thread->hasContinuationRequestFromUser($currentUser->user_id);
        }
        // スレッド主の要望状態を取得
        $threadOwner = $thread->user;
        if ($threadOwner) {
            $hasOwnerContinuationRequest = $thread->hasContinuationRequestFromUser($threadOwner->user_id);
            // 現在のユーザーがスレッド主かどうかを判定
            if ($currentUser) {
                $isCurrentUserThreadOwner = $threadOwner->user_id === $currentUser->user_id;
            }
        }
        $parentThread = $thread->parentThread;
        $continuationThread = $thread->continuationThread;
        $continuationNumber = $thread->getContinuationNumber();

        return view('threads.show', compact(
            'thread', 'users', 'currentUser', 'userReportedThread', 'userReportedThreadRejected',
            'userReportedResponses', 'userReportedResponseRejected',
            'isThreadRestricted', 'threadRestrictionReasons', 'isFavorited', 'isThreadDeletedByReport',
            'responseRestrictionData', 'lang', 'phpUploadMaxSize', 'phpPostMaxSize',
            'threadImageReportScore', 'isThreadImageBlurred', 'threadImageUrl', 'isR18Thread',
            'isResponseLimitReached', 'continuationRequestCount', 'hasUserContinuationRequest',
            'parentThread', 'continuationThread', 'isContinuationRequestLimitReached', 'continuationRequestThreshold',
            'hasOwnerContinuationRequest', 'isCurrentUserThreadOwner', 'continuationNumber'
        ));
    }

    /**
     * スレッドのレスポンスを取得する（APIエンドポイント）
     * 最新から10件ずつ取得
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getResponses($id, Request $request)
    {
        // AJAXリクエストでない場合はスレッド詳細ページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->route('threads.show', $id);
        }
        
        $thread = Thread::findOrFail($id);
        
        // IDOR防止: R18スレッドの閲覧権限をチェック（18歳未満のユーザーは閲覧不可）
        $currentUser = auth()->user();
        if (!\Illuminate\Support\Facades\Gate::forUser($currentUser)->allows('view', $thread)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return response()->json([
                'error' => \App\Services\LanguageService::trans('r18_thread_adult_only_view', $lang)
            ], 403);
        }
        
        // オフセット（既に読み込んだ件数）
        $offset = $request->get('offset', 0);
        $limit = 10; // 10件ずつ
        
        // 最新から取得（created_at降順）（翻訳用に parentResponse も取得）
        $responses = $thread->responses()
            ->with(['user', 'parentResponse'])
            ->orderBy('created_at', 'desc')
            ->skip($offset)
            ->take($limit)
            ->get();
        
        // レスポンスを逆順にして、古い順に表示できるようにする
        $responses = $responses->reverse();
        
        // ユーザー情報を取得
        $userIds = $responses->pluck('user_id')->unique()->filter()->values();
        $users = $this->buildUserMapByUserIds($userIds);
        
        // ログインユーザーの情報を取得
        $currentUser = auth()->user();
        $userReportedResponses = collect();
        $userReportedResponseRejected = [];
        
        if ($currentUser) {
            $responseReports = \App\Models\Report::where('user_id', $currentUser->user_id)
                ->whereIn('response_id', $responses->pluck('response_id'))
                ->get();
            
            $reportedResponseIds = [];
            foreach ($responseReports as $report) {
                $reportedResponseIds[] = $report->response_id;
                if ($report->approved_at && $report->is_approved === false) {
                    $userReportedResponseRejected[$report->response_id] = true;
                }
            }
            $userReportedResponses = collect($reportedResponseIds);
        }
        
        // レスポンスの制限情報を取得
        $responseIds = $responses->pluck('response_id')->toArray();
        $responseRestrictionData = [];
        
        if (!empty($responseIds)) {
            $deletedResponseIds = \App\Models\Report::whereIn('response_id', $responseIds)
                ->where('is_approved', true)
                ->pluck('response_id')
                ->toArray();
            
            $sixMonthsAgo = now()->subMonths(6);
            $restrictedReasonList = [
                'スパム・迷惑行為',
                '攻撃的・不適切な内容',
                '不適切なリンク・外部誘導',
                'コンテンツ規制違反',
                'その他'
            ];
            
            $reports = \App\Models\Report::whereIn('response_id', $responseIds)
                ->where('created_at', '>=', $sixMonthsAgo)
                ->get()
                ->groupBy('response_id');
            
            $uniqueUserIds = $reports->flatten()->pluck('user_id')->unique()->toArray();
            $userReportScores = [];
            foreach ($uniqueUserIds as $userId) {
                $userReportScores[$userId] = \App\Models\Report::calculateUserReportScore($userId);
            }
            
            foreach ($responseIds as $responseId) {
                $responseReports = $reports->get($responseId, collect());
                $isDeleted = in_array($responseId, $deletedResponseIds);
                
                $restrictedScore = 0.0;
                $ideologyScore = 0.0;
                $adultContentScore = 0.0;
                $restrictionReasons = [];
                
                if ($responseReports->isNotEmpty()) {
                    $restrictedReports = $responseReports->whereIn('reason', $restrictedReasonList)
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($restrictedReports as $report) {
                        $restrictedScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                    
                    $ideologyReports = $responseReports->where('reason', '異なる思想に関しての意見の押し付け、妨害')
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($ideologyReports as $report) {
                        $ideologyScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                    
                    $adultContentReports = $responseReports->where('reason', '成人向けコンテンツが含まれる')
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($adultContentReports as $report) {
                        $adultContentScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                    
                    $restrictedReportsForReasons = $responseReports->whereIn('reason', $restrictedReasonList);
                    foreach ($restrictedReportsForReasons as $report) {
                        if (!in_array($report->reason, $restrictionReasons)) {
                            $restrictionReasons[] = $report->reason;
                        }
                    }
                    
                    if ($ideologyScore >= 3.0) {
                        $restrictionReasons[] = '異なる思想に関しての意見の押し付け、妨害';
                    }
                    
                    if ($adultContentScore >= 2.0) {
                        $restrictionReasons[] = '成人向けコンテンツが含まれる';
                    }
                }
                
                $shouldBeHidden = $restrictedScore >= 1.0 || $ideologyScore >= 3.0 || $adultContentScore >= 2.0;
                
                $responseRestrictionData[$responseId] = [
                    'shouldBeHidden' => $shouldBeHidden,
                    'isDeletedByReport' => $isDeleted,
                    'restrictionReasons' => $restrictionReasons,
                ];
            }
        }
        
        // HTMLを生成
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $this->applyTranslationsForResponses($responses, $lang, $users);
        $html = '';
        foreach ($responses as $response) {
            $isReported = $userReportedResponses->contains($response->response_id);
            $isReportRejected = isset($userReportedResponseRejected[$response->response_id]) && $userReportedResponseRejected[$response->response_id];
            
            $html .= view('threads.partials.response-item', [
                'response' => $response,
                'users' => $users,
                'thread' => $thread,
                'isReported' => $isReported,
                'isReportRejected' => $isReportRejected,
                'lang' => $lang,
                'responseRestrictionData' => $responseRestrictionData,
                'currentUser' => $currentUser,
            ])->render();
        }
        
        // 残りのレスポンス数
        $totalResponses = $thread->responses()->count();
        $hasMore = ($offset + $limit) < $totalResponses;
        
        return response()->json([
            'html' => $html,
            'hasMore' => $hasMore,
            'offset' => $offset + $limit,
            'total' => $totalResponses,
        ]);
    }

    /**
     * 新しいレスポンスを取得する（リアルタイム更新用APIエンドポイント）
     * 指定されたレスポンスID以降の新しいレスポンスを取得
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getNewResponses($id, Request $request)
    {
        // AJAXリクエストでない場合はスレッド詳細ページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->route('threads.show', $id);
        }
        
        $thread = Thread::findOrFail($id);
        
        // IDOR防止: R18スレッドの閲覧権限をチェック（18歳未満のユーザーは閲覧不可）
        $currentUser = auth()->user();
        if (!\Illuminate\Support\Facades\Gate::forUser($currentUser)->allows('view', $thread)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return response()->json([
                'error' => \App\Services\LanguageService::trans('r18_thread_adult_only_view', $lang)
            ], 403);
        }
        
        // 最新のレスポンスID（既に表示されている最新のレスポンスID）
        $lastResponseId = (int)$request->get('last_response_id', 0);
        
        // 指定されたレスポンスID以降の新しいレスポンスを取得（created_at昇順で取得）（翻訳用に parentResponse も取得）
        $query = $thread->responses()
            ->with(['user', 'parentResponse'])
            ->orderBy('created_at', 'asc');
        
        if ($lastResponseId > 0) {
            $query->where('response_id', '>', $lastResponseId);
        }
        
        $responses = $query->get();
        
        if ($responses->isEmpty()) {
            return response()->json([
                'html' => '',
                'responses' => [],
                'latest_response_id' => $lastResponseId,
            ]);
        }
        
        // ユーザー情報を取得
        $userIds = $responses->pluck('user_id')->unique()->filter()->values();
        $users = $this->buildUserMapByUserIds($userIds);
        
        // ログインユーザーの情報を取得
        $currentUser = auth()->user();
        $userReportedResponses = collect();
        $userReportedResponseRejected = [];
        
        if ($currentUser) {
            $responseReports = \App\Models\Report::where('user_id', $currentUser->user_id)
                ->whereIn('response_id', $responses->pluck('response_id'))
                ->get();
            
            $reportedResponseIds = [];
            foreach ($responseReports as $report) {
                $reportedResponseIds[] = $report->response_id;
                if ($report->approved_at && $report->is_approved === false) {
                    $userReportedResponseRejected[$report->response_id] = true;
                }
            }
            $userReportedResponses = collect($reportedResponseIds);
        }
        
        // レスポンスの制限情報を取得
        $responseIds = $responses->pluck('response_id')->toArray();
        $responseRestrictionData = [];
        
        if (!empty($responseIds)) {
            $deletedResponseIds = \App\Models\Report::whereIn('response_id', $responseIds)
                ->where('is_approved', true)
                ->pluck('response_id')
                ->toArray();
            
            $sixMonthsAgo = now()->subMonths(6);
            $restrictedReasonList = [
                'スパム・迷惑行為',
                '攻撃的・不適切な内容',
                '不適切なリンク・外部誘導',
                'コンテンツ規制違反',
                'その他'
            ];
            
            $reports = \App\Models\Report::whereIn('response_id', $responseIds)
                ->where('created_at', '>=', $sixMonthsAgo)
                ->get()
                ->groupBy('response_id');
            
            $uniqueUserIds = $reports->flatten()->pluck('user_id')->unique()->toArray();
            $userReportScores = [];
            foreach ($uniqueUserIds as $userId) {
                $userReportScores[$userId] = \App\Models\Report::calculateUserReportScore($userId);
            }
            
            foreach ($responseIds as $responseId) {
                $responseReports = $reports->get($responseId, collect());
                $isDeleted = in_array($responseId, $deletedResponseIds);
                
                $restrictedScore = 0.0;
                $ideologyScore = 0.0;
                $adultContentScore = 0.0;
                $restrictionReasons = [];
                
                if ($responseReports->isNotEmpty()) {
                    $restrictedReports = $responseReports->whereIn('reason', $restrictedReasonList)
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($restrictedReports as $report) {
                        $restrictedScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                    
                    $ideologyReports = $responseReports->where('reason', '異なる思想に関しての意見の押し付け、妨害')
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($ideologyReports as $report) {
                        $ideologyScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                    
                    $adultContentReports = $responseReports->where('reason', '成人向けコンテンツが含まれる')
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($adultContentReports as $report) {
                        $adultContentScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                    
                    $restrictedReportsForReasons = $responseReports->whereIn('reason', $restrictedReasonList);
                    foreach ($restrictedReportsForReasons as $report) {
                        if (!in_array($report->reason, $restrictionReasons)) {
                            $restrictionReasons[] = $report->reason;
                        }
                    }
                    
                    if ($ideologyScore >= 3.0) {
                        $restrictionReasons[] = '異なる思想に関しての意見の押し付け、妨害';
                    }
                    
                    if ($adultContentScore >= 2.0) {
                        $restrictionReasons[] = '成人向けコンテンツが含まれる';
                    }
                }
                
                $shouldBeHidden = $restrictedScore >= 1.0 || $ideologyScore >= 3.0 || $adultContentScore >= 2.0;
                
                $responseRestrictionData[$responseId] = [
                    'shouldBeHidden' => $shouldBeHidden,
                    'isDeletedByReport' => $isDeleted,
                    'restrictionReasons' => $restrictionReasons,
                ];
            }
        }
        
        // HTMLを生成
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        $this->applyTranslationsForResponses($responses, $lang, $users);
        $html = '';
        foreach ($responses as $response) {
            $isReported = $userReportedResponses->contains($response->response_id);
            $isReportRejected = isset($userReportedResponseRejected[$response->response_id]) && $userReportedResponseRejected[$response->response_id];
            
            $html .= view('threads.partials.response-item', [
                'response' => $response,
                'users' => $users,
                'thread' => $thread,
                'isReported' => $isReported,
                'isReportRejected' => $isReportRejected,
                'lang' => $lang,
                'responseRestrictionData' => $responseRestrictionData,
                'currentUser' => $currentUser,
            ])->render();
        }
        
        // 最新のレスポンスIDを取得
        $latestResponseId = $responses->max('response_id') ?? $lastResponseId;
        
        return response()->json([
            'html' => $html,
            'responses' => $responses->map(function($response) {
                return ['id' => $response->response_id, 'created_at' => $response->created_at->toIso8601String()];
            })->toArray(),
            'latest_response_id' => $latestResponseId,
        ]);
    }

    /**
     * スレッドのレスポンスを検索する（APIエンドポイント）
     *
     * @param  int  $id
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function searchResponses($id, Request $request)
    {
        // AJAXリクエストでない場合はスレッド詳細ページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->route('threads.show', $id);
        }
        
        $thread = Thread::findOrFail($id);
        
        // IDOR防止: R18スレッドの閲覧権限をチェック（18歳未満のユーザーは閲覧不可）
        $currentUser = auth()->user();
        if (!\Illuminate\Support\Facades\Gate::forUser($currentUser)->allows('view', $thread)) {
            $lang = \App\Services\LanguageService::getCurrentLanguage();
            return response()->json([
                'error' => \App\Services\LanguageService::trans('r18_thread_adult_only_view', $lang)
            ], 403);
        }
        
        $query = $request->get('query', '');
        $target = $request->get('target', 'both');
        
        if (empty($query)) {
            return response()->json([
                'results' => [],
                'count' => 0,
            ]);
        }
        
        // キーワードを空白で分割（AND検索用）
        $keywords = array_filter(array_map('trim', explode(' ', $query)));
        
        // 全レスポンスを取得（userリレーションを読み込む）
        $responses = $thread->responses()
            ->with('user')
            ->orderBy('created_at', 'asc')
            ->get();
        
        // 削除されたレスポンスのIDを取得
        $deletedResponseIds = \App\Models\Report::whereIn('response_id', $responses->pluck('response_id'))
            ->where('is_approved', true)
            ->pluck('response_id')
            ->toArray();
        
        // ユーザー情報を取得
        $userIds = $responses->pluck('user_id')->unique()->filter()->values();
        $users = $this->buildUserMapByUserIds($userIds);
        
        // 検索結果をフィルタリング
        $results = [];
        $responseIndex = 0; // レスポンスの順序を記録（削除されたものも含めてカウント）
        foreach ($responses as $response) {
            // 削除されたレスポンスはスキップ
            if (in_array($response->response_id, $deletedResponseIds)) {
                $responseIndex++;
                continue;
            }
            $matchesAll = true;
            
            foreach ($keywords as $keyword) {
                $matchesKeyword = false;
                
                // 検索対象に応じてマッチング
                if ($target === 'body' || $target === 'both') {
                    if (mb_stripos($response->body, $keyword) !== false) {
                        $matchesKeyword = true;
                    }
                }
                
                if ($target === 'user' || $target === 'both') {
                    // user_idからusernameを取得して検索
                    $user = $users[$response->user_id] ?? null;
                    if ($user && mb_stripos($user->username, $keyword) !== false) {
                        $matchesKeyword = true;
                    }
                }
                
                if (!$matchesKeyword) {
                    $matchesAll = false;
                    break;
                }
            }
            
            if ($matchesAll) {
                $user = $users[$response->user_id] ?? null;
                $displayName = $user ? $user->username : '削除されたユーザー';
                if ($user && $user->display_name) {
                    $displayName = $user->display_name;
                }
                
                $results[] = [
                    'response_id' => $response->response_id,
                    'body' => $response->body,
                    'user_id' => $response->user_id,
                    'username' => $user ? $user->username : '削除されたユーザー',
                    'display_name' => $displayName,
                    'created_at' => $response->created_at->format('Y-m-d H:i:s'),
                    'response_order' => $responseIndex, // レスポンスの順序（0始まり）
                ];
            }
            
            $responseIndex++;
        }
        
        return response()->json([
            'results' => $results,
            'count' => count($results),
        ]);
    }

    /**
     * 指定されたスレッドの編集フォームを表示する
     *
     * @param  int  $id
     * @return \Illuminate\View\View
     */
    public function edit($id)
    {
        $thread = Thread::findOrFail($id);
        return view('threads.edit', compact('thread'));
    }

    /**
     * 指定されたスレッドを更新する
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function update(Request $request, $id)
    {
        $thread = Thread::findOrFail($id);

        // バリデーション
        $request->validate([
            'title' => 'required|max:50',
            'tag' => 'required|max:100',
            'is_r18' => 'nullable|boolean',
        ]);

        $lang = \App\Services\LanguageService::getCurrentLanguage();

        // R18タグ（3種類）を定義
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        
        // 未成年ユーザーがR18タグを選択できないようにする
        if (in_array($request->tag, $r18Tags)) {
            if (!auth()->check()) {
                return back()->withErrors(['tag' => \App\Services\LanguageService::trans('r18_thread_login_required', $lang)])
                    ->withInput();
            }
            
            $user = auth()->user();
            if (!$user->isAdult()) {
                return back()->withErrors(['tag' => \App\Services\LanguageService::trans('r18_thread_adult_only_view', $lang)])
                    ->withInput();
            }
        }
        
        // R18スレッドにするかどうかを判定
        $isR18 = $request->has('is_r18') && $request->is_r18 == '1';
        
        // R18タグが選択された場合は強制的にR18スレッドにする
        if (in_array($request->tag, $r18Tags)) {
            $isR18 = true;
        }
        
        // R18スレッドに変更する場合、18歳以上かどうかをチェック
        $wasR18 = $thread->is_r18;
        $isChangingToR18 = $isR18 && !$wasR18;
        
        if ($isChangingToR18) {
            if (!auth()->check()) {
                return back()->withErrors(['is_r18' => \App\Services\LanguageService::trans('r18_thread_change_login_required', $lang)])
                    ->withInput();
            }
            
            $user = auth()->user();
            if (!$user->isAdult()) {
                return back()->withErrors(['is_r18' => \App\Services\LanguageService::trans('r18_thread_adult_only_change', $lang)])
                    ->withInput();
            }
        }

        $thread->update([
            'title' => $request->title,
            'tag' => $request->tag,
            'is_r18' => $isR18,
        ]);

        // R18スレッドに変更された場合、「成人向けコンテンツが含まれる」理由の未処理通報を承認済みとして処理
        if ($isChangingToR18) {
            $this->handleAdultContentReportsOnR18Change($thread);
        }

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return redirect()->route('threads.show', $thread->thread_id)
            ->with('success', \App\Services\LanguageService::trans('thread_updated_success', $lang));
    }

    /**
     * 指定されたスレッドを削除する
     *
     * @param  int  $id
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroy($id)
    {
        $thread = Thread::findOrFail($id);
        $thread->delete();

        // キャッシュをクリア
        Cache::forget('threads_index_');
        Cache::forget('threads_latest');
        Cache::forget('threads_popular');
        Cache::forget('threads_most_responses');

        $lang = \App\Services\LanguageService::getCurrentLanguage();
        return redirect()->route('threads.index')
            ->with('success', \App\Services\LanguageService::trans('thread_deleted_success', $lang));
    }

    /**
     * カテゴリ別のスレッド一覧を表示する
     *
     * @param  string  $category
     * @return \Illuminate\View\View
     */
    public function category($category, Request $request)
    {
        // ユーザーが18歳以上かどうかを判定
        // 非ログイン時はフィルタリングしない（R18スレッドも表示）
        $isLoggedIn = auth()->check();
        $isAdult = $isLoggedIn && auth()->user() ? auth()->user()->isAdult() : false;
        
        // フィルタリングパラメータ（デフォルト設定）
        $sortBy = $request->get('sort_by');
        $period = $request->get('period');
        $completionStatus = $request->get('completion', 'all');
        
        // 現在の言語を取得
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        // スレッドを取得
        switch ($category) {
            case 'popular':
                // デフォルト設定: sort_by=popular, period=all, 全期間の閲覧数順
                $sortBy = $sortBy ?? 'popular';
                $period = $period ?? 'all';
                $threadsQuery = Thread::popular(10000);
                $title = \App\Services\LanguageService::trans('popular_threads', $lang);
                $icon = '🔥';
                break;
                
            case 'trending':
                // デフォルト設定: sort_by=popular, period=30, 1カ月の閲覧数順
                $sortBy = $sortBy ?? 'popular';
                $period = $period ?? '30';
                // trendingケースでは初期クエリを設定（後でorderByAccessCountInPeriodで処理）
                $threadsQuery = Thread::query();
                $title = \App\Services\LanguageService::trans('trending_threads', $lang);
                $icon = '📈';
                break;
                
            case 'latest':
                // デフォルト設定: sort_by=latest, スレッド作成日時順
                $sortBy = $sortBy ?? 'latest';
                $threadsQuery = Thread::latestThreads(10000);
                $title = \App\Services\LanguageService::trans('latest_threads', $lang);
                $icon = '🆕';
                break;
                
            case 'most-commented':
                $threadsQuery = Thread::mostResponses(10000);
                $title = \App\Services\LanguageService::trans('most_commented_threads', $lang);
                $icon = '💬';
                break;
                
            default:
                abort(404);
        }
        
        // R18タグのフィルタリング（未成年ログイン時のみ）
        if ($isLoggedIn && !$isAdult) {
            $threadsQuery = $threadsQuery->filterR18Threads($isAdult);
        }
        
        // 完結状態でフィルタリング
        $threadsQuery = $threadsQuery->filterByCompletion($completionStatus);
        
        // ソート処理
        if ($sortBy === 'popular') {
            // 閲覧数順
            if ($period === '30' || $period === '365' || $period === 'all') {
                $days = $period === '30' ? 30 : ($period === '365' ? 365 : null);
                // trendingケースでは既に初期クエリが設定されているので、orderByAccessCountInPeriodを使う
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod($days);
            } else {
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod(null);
            }
        } else {
            // 新しい順（デフォルト）
            $threadsQuery = $threadsQuery->orderBy('created_at', 'desc');
        }
        
        // 総件数を取得
        $totalCount = $threadsQuery->count();
        
        // 最初は20件のみ取得
        $threads = $threadsQuery->with('user')->take(20)->get();

        // 言語を一度だけ取得してビューに渡す（パフォーマンス向上）
        $lang = \App\Services\LanguageService::getCurrentLanguage();

        // スレッドの制限情報を一括取得（N+1問題を回避）
        $threadRestrictionData = $this->getThreadRestrictionData($threads);
        
        // スレッド画像の通報スコアを一括取得（N+1問題を回避）
        $threadIds = $threads->pluck('thread_id')->toArray();
        $threadImageReportScoreData = [];
        foreach ($threadIds as $threadId) {
            $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
            // スレッド画像関連の通報理由で削除された場合は画像も非表示
            $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
            $threadImageReportScoreData[$threadId] = [
                'score' => $score,
                'isBlurred' => $score >= 1.0,
                'isDeletedByImageReport' => $isDeletedByImageReport
            ];
        }

        return view('threads.category', compact('threads', 'title', 'icon', 'category', 'sortBy', 'period', 'completionStatus', 'threadRestrictionData', 'threadImageReportScoreData', 'lang', 'totalCount'));
    }

    /**
     * スレッドの制限情報を一括取得（N+1問題を回避）
     * パフォーマンス向上のため、スレッド数が多い場合は簡略化
     * 
     * @param \Illuminate\Support\Collection $threads
     * @return array
     */
    public function getThreadRestrictionData($threads)
    {
        if ($threads->isEmpty()) {
            return [];
        }

        $threadIds = $threads->pluck('thread_id')->toArray();
        $threadCount = count($threadIds);
        $restrictionData = [];

        // 通報により削除されたスレッドIDを一括取得
        $deletedThreadIds = \App\Models\Report::whereIn('thread_id', $threadIds)
            ->where('is_approved', true)
            ->pluck('thread_id')
            ->unique()
            ->toArray();

        // スレッド数が多い場合は制限情報の計算をスキップ（パフォーマンス向上）
        $maxThreadsForCheck = 100;
        if ($threadCount > $maxThreadsForCheck) {
            // すべてfalseを返す（制限情報を表示しない）
            foreach ($threadIds as $threadId) {
                $restrictionData[$threadId] = [
                    'isRestricted' => false,
                    'isDeletedByReport' => in_array($threadId, $deletedThreadIds)
                ];
            }
            return $restrictionData;
        }

        // キャッシュから取得を試みる（存在しない場合は計算）
        foreach ($threadIds as $threadId) {
            $cacheKey = 'thread_restriction_' . $threadId;
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                $restrictionData[$threadId] = [
                    'isRestricted' => $cached['isRestricted'] ?? false,
                    'isDeletedByReport' => in_array($threadId, $deletedThreadIds)
                ];
            } else {
                // キャッシュがない場合は計算（後で一括処理）
                $restrictionData[$threadId] = null; // 後で計算する
            }
        }

        // キャッシュされていないスレッドの制限情報を一括計算
        $uncachedThreadIds = array_filter($threadIds, function($threadId) use ($restrictionData) {
            return $restrictionData[$threadId] === null;
        });

        if (!empty($uncachedThreadIds)) {
            $sixMonthsAgo = now()->subMonths(6);
            $restrictedReasonList = [
                'スパム・迷惑行為',
                '攻撃的・不適切な内容',
                '不適切なリンク・外部誘導',
                'コンテンツ規制違反',
                'その他'
            ];

            // 通報データを一括取得
            $reports = \App\Models\Report::whereIn('thread_id', $uncachedThreadIds)
                ->where('created_at', '>=', $sixMonthsAgo)
                ->get()
                ->groupBy('thread_id');

            // 通報者のスコアを一括取得（キャッシュを活用）
            $uniqueUserIds = $reports->flatten()->pluck('user_id')->unique()->toArray();
            $userReportScores = [];
            foreach ($uniqueUserIds as $userId) {
                // calculateUserReportScoreは内部でキャッシュを使用している
                $userReportScores[$userId] = \App\Models\Report::calculateUserReportScore($userId);
            }

            foreach ($uncachedThreadIds as $threadId) {
                $threadReports = $reports->get($threadId, collect());
                
                $restrictedScore = 0.0;
                $ideologyScore = 0.0;
                $adultContentScore = 0.0;

                if ($threadReports->isNotEmpty()) {
                    // 特定理由によるスコア計算
                    $restrictedReports = $threadReports->whereIn('reason', $restrictedReasonList)
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($restrictedReports as $report) {
                        $restrictedScore += $userReportScores[$report->user_id] ?? 0.3;
                    }

                    // 異なる思想によるスコア計算
                    $ideologyReports = $threadReports->where('reason', '異なる思想に関しての意見の押し付け、妨害')
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($ideologyReports as $report) {
                        $ideologyScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                    
                    // 成人向けコンテンツによるスコア計算
                    $adultContentReports = $threadReports->where('reason', '成人向けコンテンツが含まれる')
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($adultContentReports as $report) {
                        $adultContentScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                }

                $isRestricted = $restrictedScore >= 1.0 || $ideologyScore >= 3.0 || $adultContentScore >= 2.0;
                
                // 通報により削除されたかどうかを判定
                $isDeletedByReport = in_array($threadId, $deletedThreadIds);
                
                $restrictionData[$threadId] = [
                    'isRestricted' => $isRestricted,
                    'isDeletedByReport' => $isDeletedByReport
                ];

                // キャッシュに保存（5分間）
                Cache::put('thread_restriction_' . $threadId, ['isRestricted' => $isRestricted], 300);
            }
        }

        return $restrictionData;
        
        /*
        if ($threads->isEmpty()) {
            return [];
        }

        $threadIds = $threads->pluck('thread_id')->toArray();
        $threadCount = count($threadIds);
        $restrictionData = [];

        // スレッド数が多い場合は制限情報の計算をスキップ（パフォーマンス向上）
        $maxThreadsForCheck = 100;
        if ($threadCount > $maxThreadsForCheck) {
            // すべてfalseを返す（制限情報を表示しない）
            foreach ($threadIds as $threadId) {
                $restrictionData[$threadId] = false;
            }
            return $restrictionData;
        }

        // キャッシュから取得を試みる（存在しない場合は計算）
        foreach ($threadIds as $threadId) {
            $cacheKey = 'thread_restriction_' . $threadId;
            $cached = Cache::get($cacheKey);
            
            if ($cached !== null) {
                $restrictionData[$threadId] = $cached['isRestricted'] ?? false;
            } else {
                // キャッシュがない場合は計算（後で一括処理）
                $restrictionData[$threadId] = null; // 後で計算する
            }
        }

        // キャッシュされていないスレッドの制限情報を一括計算
        $uncachedThreadIds = array_filter($threadIds, function($threadId) use ($restrictionData) {
            return $restrictionData[$threadId] === null;
        });

        if (!empty($uncachedThreadIds)) {
            $sixMonthsAgo = now()->subMonths(6);
            $restrictedReasonList = [
                'スパム・迷惑行為',
                '攻撃的・不適切な内容',
                '不適切なリンク・外部誘導',
                'コンテンツ規制違反',
                'その他'
            ];

            // 通報データを一括取得
            $reports = \App\Models\Report::whereIn('thread_id', $uncachedThreadIds)
                ->where('created_at', '>=', $sixMonthsAgo)
                ->get()
                ->groupBy('thread_id');

            // 通報者のスコアを一括取得（キャッシュを活用）
            $uniqueUserIds = $reports->flatten()->pluck('user_id')->unique()->toArray();
            $userReportScores = [];
            foreach ($uniqueUserIds as $userId) {
                // calculateUserReportScoreは内部でキャッシュを使用している
                $userReportScores[$userId] = \App\Models\Report::calculateUserReportScore($userId);
            }

            foreach ($uncachedThreadIds as $threadId) {
                $threadReports = $reports->get($threadId, collect());
                
                $restrictedScore = 0.0;
                $ideologyScore = 0.0;
                $adultContentScore = 0.0;

                if ($threadReports->isNotEmpty()) {
                    // 特定理由によるスコア計算
                    $restrictedReports = $threadReports->whereIn('reason', $restrictedReasonList)
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($restrictedReports as $report) {
                        $restrictedScore += $userReportScores[$report->user_id] ?? 0.3;
                    }

                    // 異なる思想によるスコア計算
                    $ideologyReports = $threadReports->where('reason', '異なる思想に関しての意見の押し付け、妨害')
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($ideologyReports as $report) {
                        $ideologyScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                    
                    // 成人向けコンテンツによるスコア計算
                    $adultContentReports = $threadReports->where('reason', '成人向けコンテンツが含まれる')
                        ->filter(function($report) {
                            return $report->is_approved === true || $report->approved_at === null;
                        });
                    
                    foreach ($adultContentReports as $report) {
                        $adultContentScore += $userReportScores[$report->user_id] ?? 0.3;
                    }
                }

                $isRestricted = $restrictedScore >= 1.0 || $ideologyScore >= 3.0 || $adultContentScore >= 2.0;
                $restrictionData[$threadId] = $isRestricted;

                // キャッシュに保存（5分間）
                Cache::put('thread_restriction_' . $threadId, ['isRestricted' => $isRestricted], 300);
            }
        }

        return $restrictionData;
        */
    }

    /**
     * ログを直接ファイルに書き込む（Laravelのログシステムに依存しない）
     * 
     * @param string $message
     * @param array $context
     * @param string $level
     */
    private function writeLogDirectly($message, $context = [], $level = 'INFO')
    {
        try {
            // 日付付きログファイルのパスを取得
            $logDir = storage_path('logs');
            $logPath = $logDir . '/laravel-' . date('Y-m-d') . '.log';
            
            // ログディレクトリが存在しない場合は作成
            if (!is_dir($logDir)) {
                @mkdir($logDir, 0755, true);
            }
            
            // ログメッセージをフォーマット（日本時間で記録）
            $timestamp = now()->setTimezone('Asia/Tokyo')->toDateTimeString();
            $contextStr = !empty($context) ? ' ' . json_encode($context, JSON_UNESCAPED_UNICODE) : '';
            $logMessage = "[{$timestamp}] local.{$level}: {$message}{$contextStr}" . PHP_EOL;
            
            // ファイルに追記（ロック付き、即座にフラッシュ）
            $result = @file_put_contents($logPath, $logMessage, FILE_APPEND | LOCK_EX);
            
            // 書き込みに失敗した場合はerror_logを使用
            if ($result === false) {
                error_log("Failed to write log to {$logPath}. Message: {$message}");
            } else {
                // 書き込み成功後、即座にフラッシュ（ファイルハンドルを開いてフラッシュ）
                $handle = @fopen($logPath, 'a');
                if ($handle) {
                    @fflush($handle);
                    @fclose($handle);
                }
            }
        } catch (\Exception $e) {
            // ログ書き込みに失敗した場合はerror_logを使用（無限ループを防ぐ）
            error_log("Failed to write log: " . $e->getMessage() . " | Original message: " . $message);
        }
    }

    /**
     * 削除されたユーザーを取得（キャッシュ付き）
     * 
     * @return \App\Models\User|null
     */
    private function getDeletedUser()
    {
        return Cache::remember('deleted_user', 3600, function () {
            return \App\Models\User::where('username', '削除されたユーザー')->first();
        });
    }

    /**
     * レスポンスのuser_idからユーザー情報を取得してマップを作成
     * 削除されたユーザーの場合も対応
     * 
     * @param array|Collection $userIds
     * @return Collection
     */
    private function buildUserMapByUserIds($userIds)
    {
        $uniqueIds = collect($userIds)->filter()->unique()->values();
        
        if ($uniqueIds->isEmpty()) {
            return collect();
        }

        $usersFromDb = \App\Models\User::whereIn('user_id', $uniqueIds)
            ->get()
            ->map(function ($user) {
                $user->residence_display = ResidenceHistory::getCountryName($user->residence);
                $user->nationality_display = ResidenceHistory::getCountryName($user->nationality);
                return $user;
            });

        // 削除されたユーザーを取得
        $deletedUser = $this->getDeletedUser();

        // user_idをキーにマッピング
        $map = collect();
        foreach ($uniqueIds as $userId) {
            $user = $usersFromDb->firstWhere('user_id', $userId);
            if ($user) {
                $map[$userId] = $user;
            } elseif ($deletedUser) {
                // ユーザーが見つからない場合は削除されたユーザーを使用
                $map[$userId] = $deletedUser;
            }
        }

        return $map;
    }

    /**
     * レスポンスに紐づくユーザー名の配列から、ビューで利用しやすいユーザーマップを生成
     * - 旧仕様の長過ぎるユーザー名は10文字でカットした名前でも検索
     * - 国コードを表示名に変換して付与
     * @deprecated user_idベースのbuildUserMapByUserIdsを使用してください
     */
    private function buildUserMapByResponseNames($userNames)
    {
        $uniqueNames = collect($userNames)->filter()->unique()->values();
        $trimmedNames = $uniqueNames->map(fn ($name) => mb_substr($name, 0, 10));

        // 検索用の名前集合（元の名前 + トリムした名前）
        $allNamesForQuery = $uniqueNames->merge($trimmedNames)->unique()->values();

        $usersFromDb = \App\Models\User::whereIn('username', $allNamesForQuery)
            ->get()
            ->map(function ($user) {
                $user->residence_display = ResidenceHistory::getCountryName($user->residence);
                $user->nationality_display = ResidenceHistory::getCountryName($user->nationality);
                return $user;
            });

        // ビューからはレスポンスの user_id で直接引けるよう、user_idをキーにマッピング（@deprecated メソッドのコメント）
        $map = collect();
        foreach ($uniqueNames as $name) {
            $exact = $usersFromDb->firstWhere('username', $name);
            $trimmed = $usersFromDb->firstWhere('username', mb_substr($name, 0, 10));
            if ($exact) {
                $map[$name] = $exact;
            } elseif ($trimmed) {
                $map[$name] = $trimmed;
            }
        }

        return $map;
    }

    /**
     * 日本語かどうかを判定
     *
     * @param string $lang
     * @return bool
     */
    private function isJapanese($lang): bool
    {
        return strtolower($lang) === 'ja';
    }

    /**
     * PHP設定値（例: "10M"）をバイト数に変換
     *
     * @param string $value
     * @return int
     */
    private function convertToBytes($value)
    {
        $value = trim($value);
        $last = strtolower($value[strlen($value) - 1]);
        $value = (int) $value;
        
        switch ($last) {
            case 'g':
                $value *= 1024;
                // fall through
            case 'm':
                $value *= 1024;
                // fall through
            case 'k':
                $value *= 1024;
        }
        
        return $value;
    }

    /**
     * 「成人向けコンテンツが含まれる」で制限がかかった場合、R18変更のお知らせを送信
     * 
     * @param Thread $thread
     * @param bool $isThreadRestricted
     * @param array $threadRestrictionReasons
     * @param array $responseRestrictionData
     * @return void
     */
    private function sendR18ChangeNotificationIfNeeded($thread, $isThreadRestricted, $threadRestrictionReasons, $responseRestrictionData)
    {
        // スレッドまたはレスポンスが「成人向けコンテンツが含まれる」で制限がかかっているか確認
        $hasAdultContentRestriction = false;
        
        if ($isThreadRestricted && in_array('成人向けコンテンツが含まれる', $threadRestrictionReasons)) {
            $hasAdultContentRestriction = true;
        } else {
            // レスポンスの制限理由を確認
            foreach ($responseRestrictionData as $data) {
                if (isset($data['restrictionReasons']) && in_array('成人向けコンテンツが含まれる', $data['restrictionReasons'])) {
                    $hasAdultContentRestriction = true;
                    break;
                }
            }
        }
        
        if (!$hasAdultContentRestriction) {
            return;
        }
        
        // スレッド主を取得
        $threadCreator = $thread->user;
        if (!$threadCreator) {
            return;
        }
        
        // スレッド主が成人ユーザーかどうかを確認
        if (!$threadCreator->isAdult()) {
            return;
        }
        
        // 既に同じスレッドに対してお知らせを送信済みかどうかを確認
        $existingMessage = \App\Models\AdminMessage::where('thread_id', $thread->thread_id)
            ->where('title_key', 'r18_change_request_title')
            ->first();
        
        if ($existingMessage) {
            return; // 既に送信済み
        }
        
        // お知らせを送信
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        // 本文を取得して、\nを実際の改行文字に変換
        $body = \App\Services\LanguageService::trans('r18_change_request_body', $lang, [
            'thread_title' => $thread->title
        ]);
        // \nを実際の改行文字に変換
        $body = str_replace('\\n', "\n", $body);
        
        \App\Models\AdminMessage::create([
            'title_key' => 'r18_change_request_title',
            'body' => $body,
            'audience' => 'members',
            'user_id' => $threadCreator->user_id,
            'thread_id' => $thread->thread_id,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
            'unlimited_reply' => false,
        ]);
    }

    /**
     * スレッド詳細表示用：表示ユーザーの言語に合わせてルーム名・リプライ本文を翻訳し display_title / display_body にセット
     * 元言語は送信者の表示言語設定。翻訳はDBに1年保存。
     *
     * @param Thread $thread
     * @param string $lang 表示言語（JA / EN）
     * @param \Illuminate\Support\Collection|null $users user_id => User のマップ（レスポンス投稿者の言語取得用）
     * @return void
     */
    private function applyTranslationsForThreadShow($thread, $lang, $users = null)
    {
        $targetLang = \App\Services\TranslationService::normalizeLang($lang);
        // 送信時の表示言語（DB保存を優先。未設定の場合は送信者の現在の言語にフォールバック）
        $threadSourceLang = $thread->source_lang !== null && $thread->source_lang !== ''
            ? \App\Services\TranslationService::normalizeLang($thread->source_lang)
            : ($thread->user ? \App\Services\TranslationService::normalizeLang($thread->user->language ?? 'EN') : 'EN');
        $thread->display_title = \App\Services\TranslationService::getTranslatedThreadTitle(
            $thread->thread_id,
            $thread->getCleanTitle(),
            $targetLang,
            $threadSourceLang
        );

        $responses = $thread->responses ?? collect();
        $users = $users ?? collect();
        foreach ($responses as $response) {
            $body = $response->body ?? '';
            $parentBody = null;
            if ($response->parent_response_id && $response->relationLoaded('parentResponse') && $response->parentResponse) {
                $parentBody = $response->parentResponse->body ?? '';
            }
            // 送信時の表示言語（DB保存を優先。送信者削除時も source_lang で判定）
            $responseSourceLang = $response->source_lang !== null && $response->source_lang !== ''
                ? \App\Services\TranslationService::normalizeLang($response->source_lang)
                : (\App\Services\TranslationService::normalizeLang(($users->get($response->user_id) ?? $response->user)?->language ?? 'EN'));
            $response->display_body = \App\Services\TranslationService::getTranslatedResponseBody(
                $response->response_id,
                $body,
                $targetLang,
                $parentBody,
                $responseSourceLang
            );
        }
    }

    /**
     * レスポンスコレクションに翻訳を適用（getResponses / getNewResponses 用）
     * 元言語は送信者の表示言語設定。翻訳はDBに1年保存。
     *
     * @param \Illuminate\Support\Collection $responses
     * @param string $lang 表示言語（JA / EN）
     * @param \Illuminate\Support\Collection $users user_id => User のマップ
     * @return void
     */
    private function applyTranslationsForResponses($responses, $lang, $users)
    {
        $targetLang = \App\Services\TranslationService::normalizeLang($lang);
        foreach ($responses as $response) {
            $body = $response->body ?? '';
            $parentBody = null;
            if ($response->parent_response_id && $response->relationLoaded('parentResponse') && $response->parentResponse) {
                $parentBody = $response->parentResponse->body ?? '';
                if (!isset($response->parentResponse->display_body)) {
                    $parentSourceLang = $response->parentResponse->source_lang !== null && $response->parentResponse->source_lang !== ''
                        ? \App\Services\TranslationService::normalizeLang($response->parentResponse->source_lang)
                        : \App\Services\TranslationService::normalizeLang(($users->get($response->parentResponse->user_id) ?? $response->parentResponse->user)?->language ?? 'EN');
                    $response->parentResponse->display_body = \App\Services\TranslationService::getTranslatedResponseBody(
                        $response->parentResponse->response_id,
                        $parentBody,
                        $targetLang,
                        null,
                        $parentSourceLang
                    );
                }
            }
            $responseSourceLang = $response->source_lang !== null && $response->source_lang !== ''
                ? \App\Services\TranslationService::normalizeLang($response->source_lang)
                : \App\Services\TranslationService::normalizeLang(($users->get($response->user_id) ?? $response->user)?->language ?? 'EN');
            $response->display_body = \App\Services\TranslationService::getTranslatedResponseBody(
                $response->response_id,
                $body,
                $targetLang,
                $parentBody,
                $responseSourceLang
            );
        }
    }

    /**
     * カテゴリ別スレッド一覧の「さらに表示」用API
     *
     * @param  string  $category
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMoreCategoryThreads($category, Request $request)
    {
        // AJAXリクエストでない場合はカテゴリページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->route('threads.category', ['category' => $category]);
        }
        
        $offset = $request->get('offset', 20);
        $limit = 20;
        
        // ユーザーが18歳以上かどうかを判定
        $isLoggedIn = auth()->check();
        $isAdult = $isLoggedIn && auth()->user() ? auth()->user()->isAdult() : false;
        
        // フィルタリングパラメータ
        $sortBy = $request->get('sort_by');
        $period = $request->get('period');
        $completionStatus = $request->get('completion', 'all');
        
        // スレッドを取得
        switch ($category) {
            case 'popular':
                $sortBy = $sortBy ?? 'popular';
                $period = $period ?? 'all';
                $threadsQuery = Thread::popular(10000);
                break;
            case 'trending':
                $sortBy = $sortBy ?? 'popular';
                $period = $period ?? '30';
                $threadsQuery = Thread::query();
                break;
            case 'latest':
                $sortBy = $sortBy ?? 'latest';
                $threadsQuery = Thread::latestThreads(10000);
                break;
            case 'most-commented':
                $threadsQuery = Thread::mostResponses(10000);
                break;
            default:
                abort(404);
        }
        
        // R18タグのフィルタリング
        if ($isLoggedIn && !$isAdult) {
            $threadsQuery = $threadsQuery->filterR18Threads($isAdult);
        }
        
        // 完結状態でフィルタリング
        $threadsQuery = $threadsQuery->filterByCompletion($completionStatus);
        
        // ソート処理
        if ($sortBy === 'popular') {
            if ($period === '30' || $period === '365' || $period === 'all') {
                $days = $period === '30' ? 30 : ($period === '365' ? 365 : null);
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod($days);
            } else {
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod(null);
            }
        } else {
            $threadsQuery = $threadsQuery->orderBy('created_at', 'desc');
        }
        
        // 総件数を取得
        $totalCount = $threadsQuery->count();
        
        // オフセットから20件取得
        $threads = $threadsQuery->with('user')->skip($offset)->take($limit)->get();
        
        // スレッドの制限情報を一括取得
        $threadRestrictionData = $this->getThreadRestrictionData($threads);
        
        // スレッド画像の通報スコアを一括取得
        $threadIds = $threads->pluck('thread_id')->toArray();
        $threadImageReportScoreData = [];
        foreach ($threadIds as $threadId) {
            $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
            // スレッド画像関連の通報理由で削除された場合は画像も非表示
            $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
            $threadImageReportScoreData[$threadId] = [
                'score' => $score,
                'isBlurred' => $score >= 1.0,
                'isDeletedByImageReport' => $isDeletedByImageReport
            ];
        }
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        // HTMLを生成
        $html = view('threads.partials.thread-item-list', [
            'threads' => $threads,
            'threadRestrictionData' => $threadRestrictionData,
            'threadImageReportScoreData' => $threadImageReportScoreData,
            'lang' => $lang,
        ])->render();
        
        $hasMore = ($offset + $limit) < $totalCount;
        
        return response()->json([
            'html' => $html,
            'hasMore' => $hasMore,
            'offset' => $offset + $limit,
            'total' => $totalCount,
        ]);
    }

    /**
     * タグ検索の「さらに表示」用API
     *
     * @param  string  $tag
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMoreTagThreads($tag, Request $request)
    {
        // AJAXリクエストでない場合はタグページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            return redirect()->route('threads.tag', ['tag' => $tag]);
        }
        
        $offset = $request->get('offset', 20);
        $limit = 20;
        
        $tag = trim($tag);
        $searchQuery = $request->get('q');
        $sortBy = $request->get('sort_by', 'popular');
        $period = $request->get('period', '30');
        $completionStatus = $request->get('completion', 'all');
        
        // ユーザーが18歳以上かどうかを判定
        $isAdult = auth()->check() && auth()->user() ? auth()->user()->isAdult() : false;
        
        // R18タグのチェック
        $r18Tags = [
            '成人向けメディア・コンテンツ・創作',
            '性体験談・性的嗜好・フェティシズム',
            'アダルト業界・風俗・ナイトワーク'
        ];
        $isR18Tag = in_array($tag, $r18Tags);
        
        if ($isR18Tag && !$isAdult) {
            return response()->json([
                'html' => '',
                'hasMore' => false,
                'offset' => $offset,
                'total' => 0,
            ]);
        }
        
        // スレッドを取得
        if ($searchQuery) {
            $threadsQuery = Thread::byTagAndSearch($tag, $searchQuery);
        } else {
            $threadsQuery = Thread::byTag($tag);
        }
        
        $isLoggedIn = auth()->check();
        if ($isLoggedIn && !$isAdult) {
            $threadsQuery = $threadsQuery->filterR18Threads($isAdult);
        }
        
        // 完結状態でフィルタリング
        $threadsQuery = $threadsQuery->filterByCompletion($completionStatus);
        
        // ソート処理
        if ($sortBy === 'popular') {
            if ($period === '30' || $period === '365' || $period === 'all') {
                $days = $period === '30' ? 30 : ($period === '365' ? 365 : null);
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod($days);
            } else {
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod(null);
            }
        } else {
            $threadsQuery = $threadsQuery->orderBy('created_at', 'desc');
        }
        
        // 総件数を取得
        $totalCount = $threadsQuery->count();
        
        // オフセットから20件取得
        $threads = $threadsQuery->with('user')->skip($offset)->take($limit)->get();
        
        // スレッドの制限情報を一括取得
        $threadRestrictionData = $this->getThreadRestrictionData($threads);
        
        // スレッド画像の通報スコアを一括取得
        $threadIds = $threads->pluck('thread_id')->toArray();
        $threadImageReportScoreData = [];
        foreach ($threadIds as $threadId) {
            $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
            // スレッド画像関連の通報理由で削除された場合は画像も非表示
            $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
            $threadImageReportScoreData[$threadId] = [
                'score' => $score,
                'isBlurred' => $score >= 1.0,
                'isDeletedByImageReport' => $isDeletedByImageReport
            ];
        }
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        // HTMLを生成
        $html = view('threads.partials.thread-item-list', [
            'threads' => $threads,
            'threadRestrictionData' => $threadRestrictionData,
            'threadImageReportScoreData' => $threadImageReportScoreData,
            'lang' => $lang,
        ])->render();
        
        $hasMore = ($offset + $limit) < $totalCount;
        
        return response()->json([
            'html' => $html,
            'hasMore' => $hasMore,
            'offset' => $offset + $limit,
            'total' => $totalCount,
        ]);
    }

    /**
     * ワード検索の「さらに表示」用API
     *
     * @param  \Illuminate\Http\Request  $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getMoreSearchThreads(Request $request)
    {
        // AJAXリクエストでない場合は検索ページにリダイレクト
        if (!$request->ajax() && !$request->wantsJson()) {
            $query = $request->get('q');
            if ($query) {
                return redirect()->route('threads.search', ['q' => $query]);
            }
            return redirect()->route('threads.index');
        }
        
        $offset = $request->get('offset', 20);
        $limit = 20;
        
        $query = $request->get('q');
        $sortBy = $request->get('sort_by', 'latest');
        $period = $request->get('period');
        $completionStatus = $request->get('completion', 'all');
        
        // ユーザーが18歳以上かどうかを判定
        $isAdult = auth()->check() && auth()->user() ? auth()->user()->isAdult() : false;
        
        // 検索クエリが空の場合は空の結果を返す
        if (!$query || trim($query) === '' || mb_strlen(trim($query)) < 2) {
            return response()->json([
                'html' => '',
                'hasMore' => false,
                'offset' => $offset,
                'total' => 0,
            ]);
        }
        
        // 検索クエリでスレッドを取得
        $threadsQuery = Thread::search($query);
        
        // R18タグのフィルタリング
        $isLoggedIn = auth()->check();
        if ($isLoggedIn && !$isAdult) {
            $threadsQuery = $threadsQuery->filterR18Threads($isAdult);
        }
        
        // 完結状態でフィルタリング
        $threadsQuery = $threadsQuery->filterByCompletion($completionStatus);
        
        // ソート処理
        if ($sortBy === 'popular') {
            if ($period === '30' || $period === '365' || $period === 'all') {
                $days = $period === '30' ? 30 : ($period === '365' ? 365 : null);
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod($days);
            } else {
                $threadsQuery = $threadsQuery->orderByAccessCountInPeriod(null);
            }
        } else {
            $threadsQuery = $threadsQuery->orderBy('created_at', 'desc');
        }
        
        // 総件数を取得
        $totalCount = $threadsQuery->count();
        
        // オフセットから20件取得
        $threads = $threadsQuery->with('user')->skip($offset)->take($limit)->get();
        
        // スレッドの制限情報を一括取得
        $threadRestrictionData = $this->getThreadRestrictionData($threads);
        
        // スレッド画像の通報スコアを一括取得
        $threadIds = $threads->pluck('thread_id')->toArray();
        $threadImageReportScoreData = [];
        foreach ($threadIds as $threadId) {
            $score = \App\Models\Report::calculateThreadImageReportScore($threadId);
            // スレッド画像関連の通報理由で削除された場合は画像も非表示
            $isDeletedByImageReport = \App\Models\Report::isThreadDeletedByImageReport($threadId);
            $threadImageReportScoreData[$threadId] = [
                'score' => $score,
                'isBlurred' => $score >= 1.0,
                'isDeletedByImageReport' => $isDeletedByImageReport
            ];
        }
        
        $lang = \App\Services\LanguageService::getCurrentLanguage();
        
        // HTMLを生成
        $html = view('threads.partials.thread-item-list', [
            'threads' => $threads,
            'threadRestrictionData' => $threadRestrictionData,
            'threadImageReportScoreData' => $threadImageReportScoreData,
            'lang' => $lang,
        ])->render();
        
        $hasMore = ($offset + $limit) < $totalCount;
        
        return response()->json([
            'html' => $html,
            'hasMore' => $hasMore,
            'offset' => $offset + $limit,
            'total' => $totalCount,
        ]);
    }

    /**
     * R18スレッドに変更された場合、「成人向けコンテンツが含まれる」理由の未処理通報を承認済みとして処理
     * 
     * @param Thread $thread
     * @return void
     */
    private function handleAdultContentReportsOnR18Change(Thread $thread)
    {
        // 「成人向けコンテンツが含まれる」理由の未処理通報を取得（通報順位を取得するため、created_atでソート）
        $reports = Report::where('thread_id', $thread->thread_id)
            ->where('reason', '成人向けコンテンツが含まれる')
            ->whereNull('approved_at')
            ->orderBy('created_at', 'asc')
            ->get();

        if ($reports->isEmpty()) {
            return;
        }

        // 通報を承認済みとして更新
        Report::where('thread_id', $thread->thread_id)
            ->where('reason', '成人向けコンテンツが含まれる')
            ->whereNull('approved_at')
            ->update([
                'is_approved' => true,
                'approved_at' => now(),
            ]);

        // 各通報者にメッセージを送信（通報順位を渡す）
        $rank = 1;
        foreach ($reports as $report) {
            if ($report->user_id) {
                $this->sendApprovalMessage($report->user_id, 'thread', $thread->title, null, $rank);
                $rank++;
            }
        }
    }

    /**
     * 了承時の自動メッセージを送信
     * 
     * @param int $userId 通報者のユーザーID
     * @param string $type 'thread' または 'response'
     * @param string $threadTitle スレッドタイトル
     * @param string|null $responseBody レスポンス本文（レスポンスの場合）
     * @param int $rank 通報順位（1から開始）
     */
    private function sendApprovalMessage(int $userId, string $type, string $threadTitle, ?string $responseBody, int $rank = 1)
    {
        $contentType = $type === 'thread' ? 'スレッド' : 'レスポンス';
        $content = $type === 'thread' 
            ? $threadTitle 
            : $threadTitle . "\n\n" . $responseBody;
        
        $bodyJa = "下記の{$contentType}において、通報いただいた内容を確認の上、違反投稿として対応いたしました。\n\n{$content}\n\nご協力ありがとうございました。";
        $bodyEn = "We have reviewed the reported content regarding the following {$contentType} and have taken action as a violation.\n\n{$content}\n\nThank you for your cooperation.";
        
        // 通報者のスコアを取得
        $userScore = Report::calculateUserReportScore($userId);
        
        // コイン数を決定
        $coinAmount = $this->calculateCoinAmount($rank, $userScore);
        
        AdminMessage::create([
            'title' => '通報内容対応完了のお知らせ',
            'body' => $bodyJa,
            'audience' => 'members', // 個人向けだが、audienceはmembersとして設定
            'user_id' => $userId,
            'published_at' => now(),
            'allows_reply' => false,
            'reply_used' => false,
            'coin_amount' => $coinAmount,
        ]);
    }

    /**
     * 通報順位とスコアに基づいてコイン数を計算
     * 
     * @param int $rank 通報順位（1から開始）
     * @param float $userScore 通報者のスコア（0.3〜0.8）
     * @return int コイン数（0〜5）
     */
    private function calculateCoinAmount(int $rank, float $userScore): int
    {
        // 6位以降はコイン配布なし
        if ($rank > 5) {
            return 0;
        }
        
        // 1位〜3位
        if ($rank <= 3) {
            if ($userScore >= 0.7 && $userScore <= 0.8) {
                return 5;
            } elseif ($userScore >= 0.5 && $userScore < 0.7) {
                return 4;
            } elseif ($userScore >= 0.3 && $userScore < 0.5) {
                return 3;
            }
        }
        
        // 4位〜5位
        if ($rank >= 4 && $rank <= 5) {
            if ($userScore >= 0.7 && $userScore <= 0.8) {
                return 3;
            } elseif ($userScore >= 0.5 && $userScore < 0.7) {
                return 2;
            } elseif ($userScore >= 0.3 && $userScore < 0.5) {
                return 1;
            }
        }
        
        // デフォルト（スコアが範囲外の場合）
        return 0;
    }

    /**
     * MIMEタイプから拡張子を取得（ユーザー入力に依存しない）
     *
     * @param string $mimeType
     * @param string $mediaType
     * @return string
     */
    private function getExtensionFromMimeType(string $mimeType, string $mediaType): string
    {
        $mimeTypeMap = [
            'image' => [
                'image/jpeg' => 'jpg',
                'image/png' => 'png',
                'image/webp' => 'webp',
            ],
            'video' => [
                'video/mp4' => 'mp4',
                'video/webm' => 'webm',
            ],
            'audio' => [
                'audio/mpeg' => 'mp3',
                'audio/mp4' => 'm4a',
                'audio/webm' => 'webm',
            ],
        ];

        $mimeTypeLower = strtolower($mimeType);
        if (isset($mimeTypeMap[$mediaType][$mimeTypeLower])) {
            return $mimeTypeMap[$mediaType][$mimeTypeLower];
        }

        // フォールバック: メディアタイプに応じたデフォルト拡張子
        return match($mediaType) {
            'image' => 'jpg',
            'video' => 'mp4',
            'audio' => 'mp3',
            default => 'bin',
        };
    }

}
