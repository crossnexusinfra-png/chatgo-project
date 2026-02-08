<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use App\Services\SecureHttpClientService;

class VeriphoneService
{
    private const API_BASE_URL = 'https://api.veriphone.io/v2/verify';
    
    // 開発環境でAPIリクエストをスキップする場合にtrueに設定
    // 仮想サーバーやWSL環境で外部APIにアクセスできない場合はtrueに設定
    private const SKIP_API_IN_DEV = true;

    /**
     * APIキーを取得
     */
    private static function getApiKey(): string
    {
        return (string) config('services.veriphone.api_key', '');
    }

    /**
     * 電話番号を検証し、VOIP番号かどうかをチェック
     *
     * @param string $phoneNumber 国際表記の電話番号（例: +14156269682）
     * @return array ['is_valid' => bool, 'is_voip' => bool, 'message' => string]
     */
    public static function verifyPhone(string $phoneNumber): array
    {
        try {
            // APIキーが空の場合のチェック
            $apiKey = self::getApiKey();
            if (empty($apiKey)) {
                Log::warning('VeriphoneService: APIキーが設定されていません');
                return [
                    'is_valid' => false,
                    'is_voip' => false,
                    'message' => '電話番号の検証に失敗しました。設定を確認してください。',
                ];
            }

            // 開発環境でAPIリクエストをスキップする場合
            if (self::SKIP_API_IN_DEV && app()->environment('local')) {
                Log::info('VeriphoneService: APIリクエストをスキップ（開発環境）', [
                    'phone' => $phoneNumber,
                ]);
                
                // 開発環境では常に有効として扱う
                return [
                    'is_valid' => true,
                    'is_voip' => false,
                    'message' => '有効な電話番号です。',
                ];
            }
            
            // ハイフン区切りの場合の処理
            if (str_contains($phoneNumber, '-')) {
                $parts = explode('-', $phoneNumber);
                $countryCode = $parts[0]; // +81
                $rest = implode('', array_slice($parts, 1)); // 8024169999 または 09012345678
                
                // 国コードの後の最初の0を除去（多くの国で必要）
                if (str_starts_with($rest, '0')) {
                    $rest = substr($rest, 1);
                }
                
                $cleanPhone = $countryCode . $rest;
            } else {
                // ハイフンがない場合
                $cleanPhone = str_replace([' ', '(', ')'], '', $phoneNumber);
                
                // 国コードの後に0が続く場合、その0を除去
                // 例: +8109012345678 → +819012345678
                if (preg_match('/^\+(\d{1,3})0(\d+)$/', $cleanPhone, $matches)) {
                    $cleanPhone = '+' . $matches[1] . $matches[2];
                }
            }
            
            // デバッグログ（必ず記録されるように）
            Log::info('VeriphoneService: 電話番号検証開始', [
                'original' => $phoneNumber,
                'cleaned' => $cleanPhone,
                'has_hyphen' => str_contains($phoneNumber, '-'),
            ]);
            
            // APIリクエスト（タイムアウトを5秒に短縮）
            try {
                // セキュアなHTTPクライアントを使用
                // URLパラメータは適切にエンコード
                $url = self::API_BASE_URL . '?key=' . urlencode(self::getApiKey()) . '&phone=' . urlencode($cleanPhone);
                $response = SecureHttpClientService::get($url, [
                    'timeout' => 5,
                ]);
                
                if (!$response) {
                    Log::error('Veriphone API secure client returned null', [
                        'phone' => $cleanPhone,
                    ]);
                    return [
                        'is_valid' => false,
                        'is_voip' => false,
                        'message' => '電話番号の検証に失敗しました。しばらくしてから再度お試しください。',
                    ];
                }
            } catch (\Illuminate\Http\Client\ConnectionException $e) {
                Log::error('Veriphone API connection timeout', [
                    'phone' => $cleanPhone,
                    'error' => $e->getMessage(),
                ]);
                
                return [
                    'is_valid' => false,
                    'is_voip' => false,
                    'message' => '電話番号の検証に失敗しました。しばらくしてから再度お試しください。',
                ];
            } catch (\Exception $e) {
                Log::error('Veriphone API request exception', [
                    'phone' => $cleanPhone,
                    'error' => $e->getMessage(),
                ]);
                
                return [
                    'is_valid' => false,
                    'is_voip' => false,
                    'message' => '電話番号の検証に失敗しました。しばらくしてから再度お試しください。',
                ];
            }
            
            // APIレスポンスのログ（必ず記録されるように）
            $responseData = $response->json();
            Log::info('VeriphoneService: APIレスポンス', [
                'phone' => $cleanPhone,
                'status_code' => $response->status(),
                'response' => $responseData,
                'is_successful' => $response->successful(),
            ]);

            if (!$response->successful()) {
                Log::warning('Veriphone API request failed', [
                    'phone' => $cleanPhone,
                    'status' => $response->status(),
                    'body' => $response->body(),
                ]);
                
                return [
                    'is_valid' => false,
                    'is_voip' => false,
                    'message' => '電話番号の検証に失敗しました。しばらくしてから再度お試しください。',
                ];
            }

            $data = $response->json();

            // APIレスポンスの確認
            if (!isset($data['status'])) {
                Log::warning('Veriphone API invalid response', [
                    'phone' => $cleanPhone,
                    'response' => $data,
                ]);
                
                return [
                    'is_valid' => false,
                    'is_voip' => false,
                    'message' => '電話番号の検証に失敗しました。',
                ];
            }

            // 電話番号が無効な場合
            if ($data['status'] !== 'valid') {
                Log::warning('Veriphone API invalid phone number', [
                    'phone' => $cleanPhone,
                    'original_phone' => $phoneNumber,
                    'status' => $data['status'] ?? 'unknown',
                    'response' => $data,
                ]);
                
                return [
                    'is_valid' => false,
                    'is_voip' => false,
                    'message' => '無効な電話番号です。',
                ];
            }

            // VOIP番号かどうかをチェック
            $isVoip = isset($data['phone_type']) && strtolower($data['phone_type']) === 'voip';

            return [
                'is_valid' => true,
                'is_voip' => $isVoip,
                'message' => $isVoip ? 'この電話番号はVOIP番号のため、SMS認証に使用できません。' : '有効な電話番号です。',
                'data' => $data,
            ];

        } catch (\Exception $e) {
            Log::error('Veriphone API exception', [
                'phone' => $phoneNumber,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [
                'is_valid' => false,
                'is_voip' => false,
                'message' => '電話番号の検証中にエラーが発生しました。しばらくしてから再度お試しください。',
            ];
        }
    }

    /**
     * 電話番号がVOIPでないことを確認
     *
     * @param string $phoneNumber 国際表記の電話番号
     * @return bool VOIPでない場合true、VOIPまたは検証失敗の場合false
     */
    public static function isNotVoip(string $phoneNumber): bool
    {
        $result = self::verifyPhone($phoneNumber);
        return $result['is_valid'] && !$result['is_voip'];
    }
}

