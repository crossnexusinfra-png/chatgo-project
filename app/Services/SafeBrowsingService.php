<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SecureHttpClientService;

class SafeBrowsingService
{
    private const API_URL = 'https://safebrowsing.googleapis.com/v4/threatMatches:find';

    /**
     * APIキーを取得
     */
    private function getApiKey(): string
    {
        return (string) config('services.safebrowsing.api_key', '');
    }

    /**
     * URLが安全かどうかをチェック
     *
     * @param string $url
     * @return array ['safe' => bool, 'error' => string|null, 'threats' => array]
     */
    public function checkUrl(string $url): array
    {
        try {
            // APIキーが空の場合のチェック
            $apiKey = $this->getApiKey();
            if (empty($apiKey)) {
                Log::warning('SafeBrowsingService: APIキーが設定されていません');
                // APIキーがない場合は安全側に倒して拒否
                return [
                    'safe' => false,
                    'error' => 'api_key_not_configured',
                    'threats' => []
                ];
            }

            Log::info('SafeBrowsingService: URL check started', ['url' => $url]);
            
            // URLを正規化
            $normalizedUrl = $this->normalizeUrl($url);
            
            if (!$normalizedUrl) {
                Log::warning('SafeBrowsingService: Invalid URL format', ['url' => $url]);
                return [
                    'safe' => false,
                    'error' => 'Invalid URL format',
                    'threats' => []
                ];
            }

            Log::debug('SafeBrowsingService: Normalized URL', ['original' => $url, 'normalized' => $normalizedUrl]);

            // Google Safe Browsing APIにリクエスト
            $requestData = [
                'client' => [
                    'clientId' => 'bbs-project',
                    'clientVersion' => '1.0.0'
                ],
                'threatInfo' => [
                    'threatTypes' => [
                        'MALWARE',
                        'SOCIAL_ENGINEERING',
                        'UNWANTED_SOFTWARE',
                        'POTENTIALLY_HARMFUL_APPLICATION'
                    ],
                    'platformTypes' => ['ANY_PLATFORM'],
                    'threatEntryTypes' => ['URL'],
                    'threatEntries' => [
                        ['url' => $normalizedUrl]
                    ]
                ]
            ];

            Log::debug('SafeBrowsingService: Sending API request', [
                'api_url' => self::API_URL,
                'url' => $normalizedUrl
            ]);

            // セキュアなHTTPクライアントを使用
            $url = self::API_URL . '?key=' . $this->getApiKey();
            $response = SecureHttpClientService::post($url, $requestData, [
                'timeout' => 10,
            ]);
            
            if (!$response) {
                Log::warning('SafeBrowsingService: Secure HTTP client returned null', [
                    'url' => $normalizedUrl
                ]);
                return [
                    'safe' => false,
                    'error' => 'api_error',
                    'threats' => []
                ];
            }

            Log::info('SafeBrowsingService: API response received', [
                'status' => $response->status(),
                'url' => $normalizedUrl
            ]);

            // API利用制限エラーのチェック
            if ($response->status() === 429) {
                Log::warning('SafeBrowsingService: Rate limit exceeded', ['url' => $normalizedUrl]);
                return [
                    'safe' => false,
                    'error' => 'rate_limit_exceeded',
                    'threats' => []
                ];
            }

            // その他のエラー
            if (!$response->successful()) {
                Log::warning('SafeBrowsingService: API error', [
                    'status' => $response->status(),
                    'body' => $response->body(),
                    'url' => $normalizedUrl
                ]);
                
                // 429以外のエラーは一時的な問題の可能性があるため、安全側に倒して拒否
                return [
                    'safe' => false,
                    'error' => 'api_error',
                    'threats' => []
                ];
            }

            $data = $response->json();

            Log::debug('SafeBrowsingService: API response data', [
                'url' => $normalizedUrl,
                'response_data' => $data
            ]);

            // マッチが見つかった場合（危険なURL）
            if (isset($data['matches']) && !empty($data['matches'])) {
                $threats = [];
                foreach ($data['matches'] as $match) {
                    $threats[] = $match['threatType'] ?? 'UNKNOWN';
                }
                
                Log::warning('SafeBrowsingService: Unsafe URL detected', [
                    'url' => $normalizedUrl,
                    'threats' => $threats,
                    'full_match_data' => $data['matches']
                ]);
                
                return [
                    'safe' => false,
                    'error' => null,
                    'threats' => $threats
                ];
            }

            // マッチが見つからない場合（安全なURL）
            Log::info('SafeBrowsingService: URL is safe', ['url' => $normalizedUrl]);
            return [
                'safe' => true,
                'error' => null,
                'threats' => []
            ];

        } catch (\Exception $e) {
            Log::error('SafeBrowsingService exception', [
                'message' => $e->getMessage(),
                'url' => $url,
                'trace' => $e->getTraceAsString()
            ]);

            // 例外が発生した場合も安全側に倒して拒否
            return [
                'safe' => false,
                'error' => 'exception',
                'threats' => []
            ];
        }
    }

    /**
     * URLを正規化
     *
     * @param string $url
     * @return string|null
     */
    private function normalizeUrl(string $url): ?string
    {
        // 前後の空白を削除
        $url = trim($url);

        // スキームがない場合はhttp://を追加（検証用）
        if (!preg_match('/^https?:\/\//i', $url)) {
            $url = 'http://' . $url;
        }

        // URLの形式を検証
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return null;
        }

        return $url;
    }

    /**
     * テキストからURLを抽出
     *
     * @param string $text
     * @return array URLの配列
     */
    public function extractUrls(string $text): array
    {
        $urls = [];
        
        // URLパターン（http/httpsで始まるURL）
        $pattern = '/https?:\/\/[^\s<>"{}|\\^`\[\]]+/i';
        
        if (preg_match_all($pattern, $text, $matches)) {
            $urls = array_unique($matches[0]);
            Log::debug('SafeBrowsingService: URLs extracted from text', [
                'count' => count($urls),
                'urls' => $urls
            ]);
        }
        
        return $urls;
    }
}

