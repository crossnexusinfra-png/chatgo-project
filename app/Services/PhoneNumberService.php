<?php

namespace App\Services;

class PhoneNumberService
{
    /**
     * 国内番号を国際表記に変換する
     *
     * @param string $countryCode 国コード（例: JP, US）
     * @param string $localNumber 国内番号
     * @return string 国際表記の電話番号
     */
    public static function convertToInternational(string $countryCode, string $localNumber): string
    {
        $countryData = self::getCountryData($countryCode);
        if (!$countryData) {
            throw new \InvalidArgumentException("無効な国コードです: {$countryCode}");
        }

        // 国内番号から不要な文字を除去
        $cleanNumber = preg_replace('/[^\d]/', '', $localNumber);
        
        // 国コードを取得
        $internationalCode = $countryData['code'];
        
        // 各国の番号フォーマットに応じて処理
        switch ($countryCode) {
            case 'JP':
                return self::formatJapaneseNumber($cleanNumber, $internationalCode);
            case 'US':
            case 'CA':
                return self::formatNorthAmericanNumber($cleanNumber, $internationalCode);
            case 'GB':
                return self::formatUKNumber($cleanNumber, $internationalCode);
            case 'DE':
                return self::formatGermanNumber($cleanNumber, $internationalCode);
            case 'FR':
                return self::formatFrenchNumber($cleanNumber, $internationalCode);
            case 'KR':
                return self::formatKoreanNumber($cleanNumber, $internationalCode);
            default:
                return self::formatGenericNumber($cleanNumber, $internationalCode);
        }
    }

    /**
     * 日本国内番号のフォーマット
     * 国際表記では先頭の0を除去する必要がある
     */
    private static function formatJapaneseNumber(string $number, string $countryCode): string
    {
        // 日本の携帯電話番号（090, 080, 070で始まる）
        // 国際表記では先頭の0を除去: 090-1234-5678 → +81-90-1234-5678
        if (preg_match('/^(090|080|070)(\d{4})(\d{4})$/', $number, $matches)) {
            $prefix = substr($matches[1], 1); // 先頭の0を除去: 090 → 90
            return "{$countryCode}-{$prefix}-{$matches[2]}-{$matches[3]}";
        }
        
        // 日本の固定電話番号（0で始まる）
        // 国際表記では先頭の0を除去: 03-1234-5678 → +81-3-1234-5678
        if (preg_match('/^0(\d{1,4})(\d{1,4})(\d{4})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }
        
        // その他の形式（先頭の0を除去）
        if (str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }
        return "{$countryCode}-{$number}";
    }

    /**
     * 北米（アメリカ・カナダ）番号のフォーマット
     */
    private static function formatNorthAmericanNumber(string $number, string $countryCode): string
    {
        // 10桁の番号（エリアコード3桁 + 交換局3桁 + 加入者4桁）
        if (preg_match('/^(\d{3})(\d{3})(\d{4})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }
        
        // 11桁の番号（1 + エリアコード3桁 + 交換局3桁 + 加入者4桁）
        if (preg_match('/^1(\d{3})(\d{3})(\d{4})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }
        
        return "{$countryCode}-{$number}";
    }

    /**
     * イギリス番号のフォーマット
     * 国際表記では先頭の0を除去する必要がある
     */
    private static function formatUKNumber(string $number, string $countryCode): string
    {
        // 先頭の0を除去
        if (str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }
        
        // 携帯電話（7で始まる10桁）
        if (preg_match('/^7(\d{3})(\d{3})(\d{3})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }
        
        // 固定電話（1, 2で始まる）
        if (preg_match('/^(\d{2,3})(\d{3,4})(\d{3,4})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }
        
        return "{$countryCode}-{$number}";
    }

    /**
     * ドイツ番号のフォーマット
     * 国際表記では先頭の0を除去する必要がある
     */
    private static function formatGermanNumber(string $number, string $countryCode): string
    {
        // 先頭の0を除去
        if (str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }
        
        // 携帯電話（15, 16, 17で始まる）
        if (preg_match('/^(15|16|17)(\d{3,4})(\d{3,4})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }
        
        // 固定電話
        if (preg_match('/^(\d{2,4})(\d{3,4})(\d{3,4})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }
        
        return "{$countryCode}-{$number}";
    }

    /**
     * フランス番号のフォーマット
     * 国際表記では先頭の0を除去する必要がある
     */
    private static function formatFrenchNumber(string $number, string $countryCode): string
    {
        // 先頭の0を除去
        if (str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }
        
        // 携帯電話（6, 7で始まる10桁）
        if (preg_match('/^([67])(\d{2})(\d{2})(\d{2})(\d{2})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}-{$matches[4]}-{$matches[5]}";
        }
        
        // 固定電話（1, 2, 3, 4, 5, 9で始まる）
        if (preg_match('/^(\d{1,2})(\d{2})(\d{2})(\d{2})(\d{2})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}-{$matches[4]}-{$matches[5]}";
        }
        
        return "{$countryCode}-{$number}";
    }

    /**
     * 中国番号のフォーマット
     */
    private static function formatChineseNumber(string $number, string $countryCode): string
    {
        // 携帯電話（11桁）
        if (preg_match('/^(\d{3})(\d{4})(\d{4})$/', $number, $matches)) {
            return "{$countryCode}-{$matches[1]}-{$matches[2]}-{$matches[3]}";
        }
        
        return "{$countryCode}-{$number}";
    }

    /**
     * 韓国番号のフォーマット
     * 国際表記では先頭の0を除去する必要がある
     */
    private static function formatKoreanNumber(string $number, string $countryCode): string
    {
        // 先頭の0を除去
        if (str_starts_with($number, '0')) {
            $number = substr($number, 1);
        }
        
        // 携帯電話（10で始まる10桁）
        if (preg_match('/^10(\d{4})(\d{4})$/', $number, $matches)) {
            return "{$countryCode}-10-{$matches[1]}-{$matches[2]}";
        }
        
        // 固定電話（2で始まる）
        if (preg_match('/^2(\d{3,4})(\d{4})$/', $number, $matches)) {
            return "{$countryCode}-2-{$matches[1]}-{$matches[2]}";
        }
        
        return "{$countryCode}-{$number}";
    }

    /**
     * 汎用的な番号フォーマット
     * 多くの国では先頭の0を除去する必要がある
     */
    private static function formatGenericNumber(string $number, string $countryCode): string
    {
        // 先頭の0を除去（多くの国で必要）
        // ただし、アメリカ・カナダ（+1）では0で始まる番号もあるため、国コードで判定
        // ここでは先頭の0を除去する（VeriphoneServiceで適切に処理される）
        if (str_starts_with($number, '0') && $countryCode !== '+1') {
            $number = substr($number, 1);
        }
        
        return "{$countryCode}-{$number}";
    }

    /**
     * 国コードから国データを取得
     */
    private static function getCountryData(string $countryCode): ?array
    {
        $countries = [
            'US' => ['name' => 'アメリカ合衆国', 'code' => '+1', 'flag' => '🇺🇸'],
            'CA' => ['name' => 'カナダ', 'code' => '+1', 'flag' => '🇨🇦'],
            'GB' => ['name' => 'イギリス', 'code' => '+44', 'flag' => '🇬🇧'],
            'DE' => ['name' => 'ドイツ', 'code' => '+49', 'flag' => '🇩🇪'],
            'FR' => ['name' => 'フランス', 'code' => '+33', 'flag' => '🇫🇷'],
            'NL' => ['name' => 'オランダ', 'code' => '+31', 'flag' => '🇳🇱'],
            'BE' => ['name' => 'ベルギー', 'code' => '+32', 'flag' => '🇧🇪'],
            'SE' => ['name' => 'スウェーデン', 'code' => '+46', 'flag' => '🇸🇪'],
            'FI' => ['name' => 'フィンランド', 'code' => '+358', 'flag' => '🇫🇮'],
            'DK' => ['name' => 'デンマーク', 'code' => '+45', 'flag' => '🇩🇰'],
            'NO' => ['name' => 'ノルウェー', 'code' => '+47', 'flag' => '🇳🇴'],
            'IS' => ['name' => 'アイスランド', 'code' => '+354', 'flag' => '🇮🇸'],
            'AT' => ['name' => 'オーストリア', 'code' => '+43', 'flag' => '🇦🇹'],
            'CH' => ['name' => 'スイス', 'code' => '+41', 'flag' => '🇨🇭'],
            'IE' => ['name' => 'アイルランド', 'code' => '+353', 'flag' => '🇮🇪'],
            'JP' => ['name' => '日本', 'code' => '+81', 'flag' => '🇯🇵'],
            'KR' => ['name' => '韓国', 'code' => '+82', 'flag' => '🇰🇷'],
            'SG' => ['name' => 'シンガポール', 'code' => '+65', 'flag' => '🇸🇬'],
            'AU' => ['name' => 'オーストラリア', 'code' => '+61', 'flag' => '🇦🇺'],
            'NZ' => ['name' => 'ニュージーランド', 'code' => '+64', 'flag' => '🇳🇿'],
        ];

        return $countries[$countryCode] ?? null;
    }

    /**
     * 国際表記の電話番号を検証する
     *
     * @param string $phoneNumber 国際表記の電話番号
     * @return bool 有効な電話番号かどうか
     */
    public static function validateInternationalNumber(string $phoneNumber): bool
    {
        // 基本的な国際表記のパターンをチェック
        $pattern = '/^\+[1-9]\d{1,14}$/';
        return preg_match($pattern, str_replace(['-', ' ', '(', ')'], '', $phoneNumber));
    }

    /**
     * 国際表記の電話番号から国コードを抽出する
     *
     * @param string $phoneNumber 国際表記の電話番号
     * @return string|null 国コード（例: JP, US）
     */
    public static function extractCountryCode(string $phoneNumber): ?string
    {
        $cleanNumber = str_replace(['-', ' ', '(', ')'], '', $phoneNumber);
        
        // 主要な国コードのパターンをチェック（20カ国に限定）
        $countryPatterns = [
            '+1' => 'US', // アメリカ・カナダ（USを優先）
            '+44' => 'GB',
            '+49' => 'DE',
            '+33' => 'FR',
            '+31' => 'NL',
            '+32' => 'BE',
            '+46' => 'SE',
            '+358' => 'FI',
            '+45' => 'DK',
            '+47' => 'NO',
            '+354' => 'IS',
            '+43' => 'AT',
            '+41' => 'CH',
            '+353' => 'IE',
            '+81' => 'JP',
            '+82' => 'KR',
            '+65' => 'SG',
            '+61' => 'AU',
            '+64' => 'NZ',
        ];

        foreach ($countryPatterns as $code => $country) {
            if (str_starts_with($cleanNumber, $code)) {
                return $country;
            }
        }

        return null;
    }
}
