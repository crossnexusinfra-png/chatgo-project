<?php

namespace App\Services;

/**
 * プロフィールの居住地（国コード）と、その日の基準となる IANA タイムゾーン。
 * 複数ゾーンがある国は代表都市で固定（ログインボーナス境界を一貫させるため）。
 */
class ResidenceTimezoneService
{
    /** @var array<string, string> */
    private const CODE_TO_TIMEZONE = [
        'JP' => 'Asia/Tokyo',
        'KR' => 'Asia/Seoul',
        'SG' => 'Asia/Singapore',
        'AU' => 'Australia/Sydney',
        'NZ' => 'Pacific/Auckland',
        'US' => 'America/New_York',
        'CA' => 'America/Toronto',
        'GB' => 'Europe/London',
        'DE' => 'Europe/Berlin',
        'FR' => 'Europe/Paris',
        'NL' => 'Europe/Amsterdam',
        'BE' => 'Europe/Brussels',
        'SE' => 'Europe/Stockholm',
        'FI' => 'Europe/Helsinki',
        'DK' => 'Europe/Copenhagen',
        'NO' => 'Europe/Oslo',
        'IS' => 'Atlantic/Reykjavik',
        'AT' => 'Europe/Vienna',
        'CH' => 'Europe/Zurich',
        'IE' => 'Europe/Dublin',
        'OTHER' => 'UTC',
    ];

    public static function timezoneForResidence(?string $residenceCode): string
    {
        $code = $residenceCode ?? 'OTHER';

        return self::CODE_TO_TIMEZONE[$code] ?? 'UTC';
    }
}
