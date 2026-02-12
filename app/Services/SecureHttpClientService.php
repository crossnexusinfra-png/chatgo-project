<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;

class SecureHttpClientService
{
    /**
     * 許可されたドメインのホワイトリスト（デフォルト）
     */
    private static function getDefaultAllowedDomains(): array
    {
        return [
            'safebrowsing.googleapis.com',
            'api.veriphone.io',
            'api.openai.com',
        ];
    }

    /**
     * 内部IPアドレスの範囲（プライベートIP）
     */
    private const PRIVATE_IP_RANGES = [
        '10.0.0.0/8',
        '172.16.0.0/12',
        '192.168.0.0/16',
        '127.0.0.0/8',
        '169.254.0.0/16', // Link-local
        '::1/128', // IPv6 localhost
        'fc00::/7', // IPv6 private
        'fe80::/10', // IPv6 link-local
    ];

    /**
     * デフォルトのタイムアウト（秒）
     */
    private static function getDefaultTimeout(): int
    {
        return (int) config('security.http_client.timeout', 10);
    }

    /**
     * デフォルトの最大リダイレクト回数
     */
    private static function getDefaultMaxRedirects(): int
    {
        return (int) config('security.http_client.max_redirects', 0);
    }

    /**
     * デフォルトの最大レスポンスサイズ（バイト）
     */
    private static function getDefaultMaxResponseSize(): int
    {
        return (int) config('security.http_client.max_response_size', 10 * 1024 * 1024);
    }

    /**
     * セキュアなHTTP GETリクエスト
     *
     * @param string $url
     * @param array $options
     * @return \Illuminate\Http\Client\Response|null
     */
    public static function get(string $url, array $options = []): ?\Illuminate\Http\Client\Response
    {
        return self::request('GET', $url, $options);
    }

    /**
     * セキュアなHTTP POSTリクエスト
     *
     * @param string $url
     * @param array $data
     * @param array $options
     * @return \Illuminate\Http\Client\Response|null
     */
    public static function post(string $url, array $data = [], array $options = []): ?\Illuminate\Http\Client\Response
    {
        $options['data'] = $data;
        return self::request('POST', $url, $options);
    }

    /**
     * セキュアなHTTPリクエスト
     *
     * @param string $method
     * @param string $url
     * @param array $options
     * @return \Illuminate\Http\Client\Response|null
     */
    private static function request(string $method, string $url, array $options = []): ?\Illuminate\Http\Client\Response
    {
        try {
            // URLの検証
            if (!self::validateUrl($url)) {
                Log::warning('SecureHttpClientService: Invalid URL', ['url' => $url]);
                return null;
            }

            // ドメインホワイトリストチェック
            $domain = self::extractDomain($url);
            if (!self::isDomainAllowed($domain)) {
                Log::warning('SecureHttpClientService: Domain not in whitelist', [
                    'url' => $url,
                    'domain' => $domain,
                ]);
                return null;
            }

            // DNS解決と内部IPチェック
            $ip = self::resolveDomainToIp($domain);
            if ($ip && self::isPrivateIp($ip)) {
                Log::warning('SecureHttpClientService: Private IP detected', [
                    'url' => $url,
                    'domain' => $domain,
                    'ip' => $ip,
                ]);
                return null;
            }

            // オプションの設定
            $timeout = $options['timeout'] ?? self::getDefaultTimeout();
            $maxRedirects = $options['max_redirects'] ?? self::getDefaultMaxRedirects();
            $maxResponseSize = $options['max_response_size'] ?? self::getDefaultMaxResponseSize();

            // HTTPクライアントの設定
            $client = Http::timeout($timeout);

            // カスタムヘッダー（オプション）
            if (!empty($options['headers']) && is_array($options['headers'])) {
                $client = $client->withHeaders($options['headers']);
            }

            // JSONボディで送信（オプション）
            if (!empty($options['json'])) {
                $client = $client->asJson();
            }
            
            // リダイレクトの制限
            if ($maxRedirects === 0) {
                // リダイレクトを完全に禁止
                $client = $client->withoutRedirecting();
            } else {
                // リダイレクト回数を制限
                $client = $client->withOptions([
                    'allow_redirects' => [
                        'max' => $maxRedirects,
                        'strict' => true,
                        'referer' => false,
                    ],
                ]);
            }

            // リクエスト実行
            $response = match(strtoupper($method)) {
                'GET' => $client->get($url, $options['params'] ?? []),
                'POST' => $client->post($url, $options['data'] ?? []),
                'PUT' => $client->put($url, $options['data'] ?? []),
                'DELETE' => $client->delete($url, $options['data'] ?? []),
                default => null,
            };

            if (!$response) {
                return null;
            }

            // レスポンスサイズのチェック
            $contentLength = $response->header('Content-Length');
            if ($contentLength && (int)$contentLength > $maxResponseSize) {
                Log::warning('SecureHttpClientService: Response size exceeds limit', [
                    'url' => $url,
                    'content_length' => $contentLength,
                    'max_size' => $maxResponseSize,
                ]);
                return null;
            }

            // 実際のレスポンスボディサイズをチェック
            // 注意: body()を呼び出すと全体がメモリに読み込まれるため、
            // 大きなレスポンスの場合はContent-Lengthヘッダーで事前にチェック
            $body = $response->body();
            $bodySize = strlen($body);
            if ($bodySize > $maxResponseSize) {
                Log::warning('SecureHttpClientService: Response body size exceeds limit', [
                    'url' => $url,
                    'body_size' => $bodySize,
                    'max_size' => $maxResponseSize,
                ]);
                return null;
            }

            return $response;

        } catch (\Exception $e) {
            Log::error('SecureHttpClientService: Request failed', [
                'url' => $url,
                'method' => $method,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            return null;
        }
    }

    /**
     * URLの検証
     *
     * @param string $url
     * @return bool
     */
    private static function validateUrl(string $url): bool
    {
        // URLの形式を検証
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            return false;
        }

        // 許可されたスキームのみ（http, https）
        $parsed = parse_url($url);
        if (!isset($parsed['scheme']) || !in_array(strtolower($parsed['scheme']), ['http', 'https'])) {
            return false;
        }

        return true;
    }

    /**
     * URLからドメインを抽出
     *
     * @param string $url
     * @return string|null
     */
    private static function extractDomain(string $url): ?string
    {
        $parsed = parse_url($url);
        if (!isset($parsed['host'])) {
            return null;
        }

        // ポート番号を除去
        $host = $parsed['host'];
        if (($pos = strpos($host, ':')) !== false) {
            $host = substr($host, 0, $pos);
        }

        return $host;
    }

    /**
     * ドメインがホワイトリストに含まれているかチェック
     *
     * @param string|null $domain
     * @return bool
     */
    private static function isDomainAllowed(?string $domain): bool
    {
        if (!$domain) {
            return false;
        }

        // ホワイトリストを取得（設定ファイルとデフォルトをマージ）
        $allowedDomains = self::getAllowedDomains();

        // 完全一致またはサブドメインをチェック
        foreach ($allowedDomains as $allowedDomain) {
            if ($domain === $allowedDomain || str_ends_with($domain, '.' . $allowedDomain)) {
                return true;
            }
        }

        return false;
    }

    /**
     * ドメインをIPアドレスに解決
     *
     * @param string $domain
     * @return string|null
     */
    private static function resolveDomainToIp(string $domain): ?string
    {
        // キャッシュをチェック（5分間キャッシュ）
        $cacheKey = 'dns_resolve_' . md5($domain);
        $cachedIp = Cache::get($cacheKey);
        if ($cachedIp !== null) {
            return $cachedIp;
        }

        // DNS解決
        $ip = gethostbyname($domain);

        // 解決に失敗した場合（ドメイン名がそのまま返される）
        if ($ip === $domain) {
            return null;
        }

        // IPv6の場合は別の方法で解決
        if (filter_var($domain, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            $records = dns_get_record($domain, DNS_AAAA);
            if (!empty($records) && isset($records[0]['ipv6'])) {
                $ip = $records[0]['ipv6'];
            }
        }

        // キャッシュに保存（5分間）
        Cache::put($cacheKey, $ip, now()->addMinutes(5));

        return $ip;
    }

    /**
     * IPアドレスがプライベートIPかどうかをチェック
     *
     * @param string|null $ip
     * @return bool
     */
    private static function isPrivateIp(?string $ip): bool
    {
        if (!$ip) {
            return false;
        }

        // IPv4の場合
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            return self::isPrivateIpv4($ip);
        }

        // IPv6の場合
        if (filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
            return self::isPrivateIpv6($ip);
        }

        return false;
    }

    /**
     * IPv4アドレスがプライベートIPかどうかをチェック
     *
     * @param string $ip
     * @return bool
     */
    private static function isPrivateIpv4(string $ip): bool
    {
        // 10.0.0.0/8
        if (str_starts_with($ip, '10.')) {
            return true;
        }

        // 172.16.0.0/12
        if (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip)) {
            return true;
        }

        // 192.168.0.0/16
        if (str_starts_with($ip, '192.168.')) {
            return true;
        }

        // 127.0.0.0/8
        if (str_starts_with($ip, '127.')) {
            return true;
        }

        // 169.254.0.0/16 (Link-local)
        if (str_starts_with($ip, '169.254.')) {
            return true;
        }

        return false;
    }

    /**
     * IPv6アドレスがプライベートIPかどうかをチェック
     *
     * @param string $ip
     * @return bool
     */
    private static function isPrivateIpv6(string $ip): bool
    {
        // ::1 (localhost)
        if ($ip === '::1') {
            return true;
        }

        // fc00::/7 (Unique Local Address)
        if (preg_match('/^fc[0-9a-f]{2}:/i', $ip)) {
            return true;
        }

        // fe80::/10 (Link-local)
        if (preg_match('/^fe[89ab][0-9a-f]:/i', $ip)) {
            return true;
        }

        return false;
    }

    /**
     * ホワイトリストにドメインを追加（設定ファイルから読み込む）
     *
     * @return array
     */
    public static function getAllowedDomains(): array
    {
        $configDomains = config('security.allowed_domains', []);
        return array_merge(self::getDefaultAllowedDomains(), $configDomains);
    }
}
