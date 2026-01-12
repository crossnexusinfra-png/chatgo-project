<?php

namespace App\Services;

use App\Models\Response;
use Illuminate\Support\Facades\DB;

class SpamDetectionService
{
    /**
     * NGワードリスト
     */
    private const NG_WORDS = [
        '即金',
        'すぐ稼げる',
        '今すぐ登録',
        '限定オファー',
        'LINE追加',
        '投資で簡単',
        '副収入が確実',
        '誰でも稼げる',
        '100%儲かる',
        'click here',
        'join now',
        'make money fast',
        'crypto giveaway',
        'free crypto',
        'free bitcoin',
        'crypto airdrop',
        'claim your reward',
        'visit this link',
        'limited time offer',
    ];

    /**
     * 類似率の閾値（Levenshtein距離ベース、80%）
     */
    private const SIMILARITY_RATE_THRESHOLD = 80.0;

    /**
     * 3-gram Jaccard類似率の閾値（70%）
     */
    private const TRIGRAM_JACCARD_RATE_THRESHOLD = 70.0;

    /**
     * チェック対象の時間範囲（12時間）
     */
    private const TIME_RANGE_HOURS = 12;

    /**
     * URL投稿の最大回数（1日あたり）
     */
    private const MAX_URL_POSTS_PER_DAY = 5;

    /**
     * スパム判定を行う
     *
     * @param string $body 投稿内容
     * @param string $userName ユーザー名
     * @param array $urls URLの配列（オプション）
     * @return array ['is_spam' => bool, 'reason' => string|null]
     */
    public function checkSpam(string $body, string $userName, array $urls = []): array
    {
        // userNameからuserIdを取得
        $user = \App\Models\User::where('username', $userName)->first();
        $userId = $user ? $user->user_id : null;
        
        if (!$userId) {
            // ユーザーが見つからない場合はスパムチェックをスキップ
            return ['is_spam' => false, 'reason' => null];
        }
        
        // NGワードチェック（完全一致）
        $ngWordResult = $this->checkNgWordsPrivate($body);
        if ($ngWordResult['is_spam']) {
            return $ngWordResult;
        }

        // 類似率チェック（12時間以内の投稿内容 + NGワード）
        $similarityResult = $this->checkSimilarity($body, $userId);
        if ($similarityResult['is_spam']) {
            return $similarityResult;
        }

        // URL類似度チェック
        if (!empty($urls)) {
            $urlSimilarityResult = $this->checkUrlSimilarity($urls, $userId);
            if ($urlSimilarityResult['is_spam']) {
                return $urlSimilarityResult;
            }

            // URL投稿回数チェック
            $urlPostCountResult = $this->checkUrlPostCount($userId);
            if ($urlPostCountResult['is_spam']) {
                return $urlPostCountResult;
            }
        }

        return ['is_spam' => false, 'reason' => null];
    }

    /**
     * NGワードチェック（public版、bio用）
     *
     * @param string $body 投稿内容
     * @return array ['is_spam' => bool, 'reason' => string|null]
     */
    public function checkNgWords(string $body): array
    {
        return $this->checkNgWordsPrivate($body);
    }

    /**
     * NGワードチェック
     *
     * @param string $body 投稿内容
     * @return array ['is_spam' => bool, 'reason' => string|null]
     */
    private function checkNgWordsPrivate(string $body): array
    {
        $bodyLower = mb_strtolower($body, 'UTF-8');
        
        foreach (self::NG_WORDS as $ngWord) {
            $ngWordLower = mb_strtolower($ngWord, 'UTF-8');
            if (mb_strpos($bodyLower, $ngWordLower) !== false) {
                return [
                    'is_spam' => true,
                    'reason' => 'ng_word',
                    'ng_word' => $ngWord,
                ];
            }
        }

        return ['is_spam' => false, 'reason' => null];
    }

    /**
     * 類似率チェック
     * 同一ユーザーが12時間以内に類似のレスポンスを投稿していないか、NGワードと類似していないかチェック
     *
     * @param string $body 投稿内容
     * @param string $userName ユーザー名
     * @return array ['is_spam' => bool, 'reason' => string|null]
     */
    private function checkSimilarity(string $body, ?int $userId): array
    {
        // NGワードとの類似率チェック
        foreach (self::NG_WORDS as $ngWord) {
            // Levenshtein距離ベースの類似率
            $levenshteinRate = $this->calculateSimilarityRate($body, $ngWord);
            if ($levenshteinRate >= self::SIMILARITY_RATE_THRESHOLD) {
                return [
                    'is_spam' => true,
                    'reason' => 'similarity',
                    'similarity_rate' => $levenshteinRate,
                    'matched_text' => $ngWord,
                    'method' => 'levenshtein',
                ];
            }

            // 3文字以上の場合は3-gram Jaccardもチェック
            if (mb_strlen($body, 'UTF-8') >= 3 && mb_strlen($ngWord, 'UTF-8') >= 3) {
                $jaccardRate = $this->calculateTrigramJaccardRate($body, $ngWord);
                if ($jaccardRate >= self::TRIGRAM_JACCARD_RATE_THRESHOLD) {
                    return [
                        'is_spam' => true,
                        'reason' => 'similarity',
                        'similarity_rate' => $jaccardRate,
                        'matched_text' => $ngWord,
                        'method' => 'trigram_jaccard',
                    ];
                }
            }
        }

        // 12時間以内の同一ユーザーのレスポンスを取得
        $timeRange = now()->subHours(self::TIME_RANGE_HOURS);
        
        if (!$userId) {
            return ['is_spam' => false, 'reason' => null];
        }
        
        $recentResponses = Response::where('user_id', $userId)
            ->where('created_at', '>=', $timeRange)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($recentResponses->isEmpty()) {
            return ['is_spam' => false, 'reason' => null];
        }

        // 各レスポンスとの類似率を計算
        foreach ($recentResponses as $recentResponse) {
            // Levenshtein距離ベースの類似率
            $levenshteinRate = $this->calculateSimilarityRate($body, $recentResponse->body);
            if ($levenshteinRate >= self::SIMILARITY_RATE_THRESHOLD) {
                return [
                    'is_spam' => true,
                    'reason' => 'similarity',
                    'similarity_rate' => $levenshteinRate,
                    'method' => 'levenshtein',
                ];
            }

            // 3文字以上の場合は3-gram Jaccardもチェック
            if (mb_strlen($body, 'UTF-8') >= 3 && mb_strlen($recentResponse->body, 'UTF-8') >= 3) {
                $jaccardRate = $this->calculateTrigramJaccardRate($body, $recentResponse->body);
                if ($jaccardRate >= self::TRIGRAM_JACCARD_RATE_THRESHOLD) {
                    return [
                        'is_spam' => true,
                        'reason' => 'similarity',
                        'similarity_rate' => $jaccardRate,
                        'method' => 'trigram_jaccard',
                    ];
                }
            }
        }

        return ['is_spam' => false, 'reason' => null];
    }

    /**
     * 自己紹介文（bio）専用の類似率チェック
     * NGワードとの類似率のみをチェック（過去の投稿チェックは行わない）
     *
     * @param string $bio 自己紹介文
     * @return array ['is_spam' => bool, 'reason' => string|null]
     */
    public function checkBioSimilarity(string $bio): array
    {
        // NGワードとの類似率チェック
        foreach (self::NG_WORDS as $ngWord) {
            // Levenshtein距離ベースの類似率
            $levenshteinRate = $this->calculateSimilarityRate($bio, $ngWord);
            if ($levenshteinRate >= self::SIMILARITY_RATE_THRESHOLD) {
                return [
                    'is_spam' => true,
                    'reason' => 'similarity',
                    'similarity_rate' => $levenshteinRate,
                    'matched_text' => $ngWord,
                    'method' => 'levenshtein',
                ];
            }

            // 3文字以上の場合は3-gram Jaccardもチェック
            if (mb_strlen($bio, 'UTF-8') >= 3 && mb_strlen($ngWord, 'UTF-8') >= 3) {
                $jaccardRate = $this->calculateTrigramJaccardRate($bio, $ngWord);
                if ($jaccardRate >= self::TRIGRAM_JACCARD_RATE_THRESHOLD) {
                    return [
                        'is_spam' => true,
                        'reason' => 'similarity',
                        'similarity_rate' => $jaccardRate,
                        'matched_text' => $ngWord,
                        'method' => 'trigram_jaccard',
                    ];
                }
            }
        }

        return ['is_spam' => false, 'reason' => null];
    }

    /**
     * 2つの文字列の類似率を計算（Levenshtein距離を使用）
     * 計算式：(距離/文字数(比較文字列の多い方))×100 = 類似率[%]
     *
     * @param string $str1 文字列1
     * @param string $str2 文字列2
     * @return float 類似率[%]（0.0～100.0）
     */
    private function calculateSimilarityRate(string $str1, string $str2): float
    {
        // 空文字列の場合は0%を返す
        if (empty($str1) && empty($str2)) {
            return 100.0;
        }
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }

        // 文字列を正規化（空白を除去、小文字に変換）
        $str1 = mb_strtolower(trim($str1), 'UTF-8');
        $str2 = mb_strtolower(trim($str2), 'UTF-8');

        // 完全一致の場合は100%を返す
        if ($str1 === $str2) {
            return 100.0;
        }

        // 文字数を取得（比較文字列の多い方）
        $len1 = mb_strlen($str1, 'UTF-8');
        $len2 = mb_strlen($str2, 'UTF-8');
        $maxLen = max($len1, $len2);
        
        if ($maxLen === 0) {
            return 100.0;
        }

        // levenshtein距離を計算
        $distance = levenshtein($str1, $str2);
        
        // 類似率を計算：(距離/文字数(比較文字列の多い方))×100
        // 類似率が高い = 距離が小さい = より類似している
        // 類似率 = (1 - (距離 / 最大長)) × 100
        $similarityRate = (1.0 - ($distance / $maxLen)) * 100.0;
        
        return max(0.0, min(100.0, $similarityRate));
    }

    /**
     * 2つの文字列の3-gram Jaccard類似率を計算
     * Jaccard係数 = (共通の3-gram数) / (両方の3-gramの和集合の数)
     * 類似率 = Jaccard係数 × 100
     *
     * @param string $str1 文字列1
     * @param string $str2 文字列2
     * @return float 類似率[%]（0.0～100.0）
     */
    private function calculateTrigramJaccardRate(string $str1, string $str2): float
    {
        // 空文字列の場合は0%を返す
        if (empty($str1) || empty($str2)) {
            return 0.0;
        }

        // 文字列を正規化（小文字に変換）
        $str1 = mb_strtolower(trim($str1), 'UTF-8');
        $str2 = mb_strtolower(trim($str2), 'UTF-8');

        // 3文字未満の場合は0%を返す
        if (mb_strlen($str1, 'UTF-8') < 3 || mb_strlen($str2, 'UTF-8') < 3) {
            return 0.0;
        }

        // 3-gramを生成
        $trigrams1 = $this->generateTrigrams($str1);
        $trigrams2 = $this->generateTrigrams($str2);

        if (empty($trigrams1) || empty($trigrams2)) {
            return 0.0;
        }

        // 共通の3-gram数を計算
        $intersection = count(array_intersect($trigrams1, $trigrams2));
        
        // 和集合の3-gram数を計算
        $union = count(array_unique(array_merge($trigrams1, $trigrams2)));

        if ($union === 0) {
            return 0.0;
        }

        // Jaccard係数を計算
        $jaccard = $intersection / $union;
        
        // 類似率を計算（%）
        $similarityRate = $jaccard * 100.0;
        
        return max(0.0, min(100.0, $similarityRate));
    }

    /**
     * 文字列から3-gramを生成
     *
     * @param string $str 文字列
     * @return array 3-gramの配列
     */
    private function generateTrigrams(string $str): array
    {
        $trigrams = [];
        $len = mb_strlen($str, 'UTF-8');
        
        if ($len < 3) {
            return [];
        }

        for ($i = 0; $i <= $len - 3; $i++) {
            $trigram = mb_substr($str, $i, 3, 'UTF-8');
            $trigrams[] = $trigram;
        }

        return $trigrams;
    }

    /**
     * 2つの文字列の類似度を計算（levenshtein距離を使用）
     * 後方互換性のため残す（URL類似度チェックで使用）
     *
     * @param string $str1 文字列1
     * @param string $str2 文字列2
     * @return float 類似度（0.0～1.0）
     */
    private function calculateSimilarity(string $str1, string $str2): float
    {
        $rate = $this->calculateSimilarityRate($str1, $str2);
        return $rate / 100.0;
    }

    /**
     * URL類似度チェック
     * 同一ユーザーが12時間以内に類似のURLを含むレスポンスを投稿していないかチェック
     *
     * @param array $urls チェック対象のURL配列
     * @param string $userName ユーザー名
     * @return array ['is_spam' => bool, 'reason' => string|null]
     */
    private function checkUrlSimilarity(array $urls, ?int $userId): array
    {
        // 12時間以内の同一ユーザーのレスポンスを取得
        $timeRange = now()->subHours(self::TIME_RANGE_HOURS);
        
        if (!$userId) {
            return ['is_spam' => false, 'reason' => null];
        }
        
        $recentResponses = Response::where('user_id', $userId)
            ->where('created_at', '>=', $timeRange)
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($recentResponses->isEmpty()) {
            return ['is_spam' => false, 'reason' => null];
        }

        // 各レスポンスからURLを抽出して類似度をチェック
        $safeBrowsingService = new \App\Services\SafeBrowsingService();
        
        foreach ($recentResponses as $recentResponse) {
            $recentUrls = $safeBrowsingService->extractUrls($recentResponse->body);
            
            if (empty($recentUrls)) {
                continue;
            }

            // 現在のURLと過去のURLを比較
            foreach ($urls as $currentUrl) {
                $normalizedCurrentUrl = $this->normalizeUrlForComparison($currentUrl);
                
                foreach ($recentUrls as $recentUrl) {
                    $normalizedRecentUrl = $this->normalizeUrlForComparison($recentUrl);
                    
                    // Levenshtein距離ベースの類似率
                    $levenshteinRate = $this->calculateSimilarityRate($normalizedCurrentUrl, $normalizedRecentUrl);
                    if ($levenshteinRate >= self::SIMILARITY_RATE_THRESHOLD) {
                        return [
                            'is_spam' => true,
                            'reason' => 'url_similarity',
                            'similarity_rate' => $levenshteinRate,
                            'url' => $currentUrl,
                            'method' => 'levenshtein',
                        ];
                    }

                    // 3文字以上の場合は3-gram Jaccardもチェック
                    if (mb_strlen($normalizedCurrentUrl, 'UTF-8') >= 3 && mb_strlen($normalizedRecentUrl, 'UTF-8') >= 3) {
                        $jaccardRate = $this->calculateTrigramJaccardRate($normalizedCurrentUrl, $normalizedRecentUrl);
                        if ($jaccardRate >= self::TRIGRAM_JACCARD_RATE_THRESHOLD) {
                            return [
                                'is_spam' => true,
                                'reason' => 'url_similarity',
                                'similarity_rate' => $jaccardRate,
                                'url' => $currentUrl,
                                'method' => 'trigram_jaccard',
                            ];
                        }
                    }
                }
            }
        }

        return ['is_spam' => false, 'reason' => null];
    }

    /**
     * URLを比較用に正規化
     *
     * @param string $url URL
     * @return string 正規化されたURL
     */
    private function normalizeUrlForComparison(string $url): string
    {
        // 前後の空白を削除
        $url = trim($url);
        
        // 小文字に変換
        $url = mb_strtolower($url, 'UTF-8');
        
        // 末尾のスラッシュを除去
        $url = rtrim($url, '/');
        
        // フラグメント（#以降）を除去
        $pos = mb_strpos($url, '#');
        if ($pos !== false) {
            $url = mb_substr($url, 0, $pos);
        }
        
        // クエリパラメータは保持（URLの一部として重要）
        // ただし、順序を正規化する場合はここでソートする
        
        return $url;
    }

    /**
     * URL投稿回数チェック
     * 同一ユーザーが今日（標準時の0時から24時まで）にURLを含むレスポンスを5回以上投稿していないかチェック
     *
     * @param string $userName ユーザー名
     * @return array ['is_spam' => bool, 'reason' => string|null]
     */
    private function checkUrlPostCount(?int $userId): array
    {
        // 今日の0時（標準時）を取得
        $todayStart = now()->startOfDay();
        $todayEnd = now()->endOfDay();
        
        // 今日の同一ユーザーのレスポンスを取得
        if (!$userId) {
            return ['is_spam' => false, 'reason' => null];
        }
        
        $todayResponses = Response::where('user_id', $userId)
            ->whereBetween('created_at', [$todayStart, $todayEnd])
            ->whereNotNull('body')
            ->where('body', '!=', '')
            ->orderBy('created_at', 'desc')
            ->get();

        if ($todayResponses->isEmpty()) {
            return ['is_spam' => false, 'reason' => null];
        }

        // URLを含むレスポンスの数をカウント
        $safeBrowsingService = new \App\Services\SafeBrowsingService();
        $urlPostCount = 0;
        
        foreach ($todayResponses as $response) {
            $urls = $safeBrowsingService->extractUrls($response->body);
            if (!empty($urls)) {
                $urlPostCount++;
            }
        }

        // 5回以上の場合にブロック
        if ($urlPostCount >= self::MAX_URL_POSTS_PER_DAY) {
            return [
                'is_spam' => true,
                'reason' => 'url_post_limit',
                'count' => $urlPostCount,
                'limit' => self::MAX_URL_POSTS_PER_DAY,
            ];
        }

        return ['is_spam' => false, 'reason' => null];
    }
}

