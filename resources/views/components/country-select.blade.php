@props(['name' => 'country', 'value' => '', 'required' => false, 'class' => ''])

@php
$lang = $lang ?? \App\Services\LanguageService::getCurrentLanguage();
$countries = [
    'US' => ['code' => '+1', 'flag' => '🇺🇸'],      // アメリカ
    'CA' => ['code' => '+1', 'flag' => '🇨🇦'],      // カナダ
    'GB' => ['code' => '+44', 'flag' => '🇬🇧'],     // イギリス
    'DE' => ['code' => '+49', 'flag' => '🇩🇪'],     // ドイツ
    'FR' => ['code' => '+33', 'flag' => '🇫🇷'],     // フランス
    'NL' => ['code' => '+31', 'flag' => '🇳🇱'],     // オランダ
    'BE' => ['code' => '+32', 'flag' => '🇧🇪'],     // ベルギー
    'SE' => ['code' => '+46', 'flag' => '🇸🇪'],     // スウェーデン
    'FI' => ['code' => '+358', 'flag' => '🇫🇮'],    // フィンランド
    'DK' => ['code' => '+45', 'flag' => '🇩🇰'],     // デンマーク
    'NO' => ['code' => '+47', 'flag' => '🇳🇴'],     // ノルウェー
    'IS' => ['code' => '+354', 'flag' => '🇮🇸'],    // アイスランド
    'AT' => ['code' => '+43', 'flag' => '🇦🇹'],     // オーストリア
    'CH' => ['code' => '+41', 'flag' => '🇨🇭'],     // スイス
    'IE' => ['code' => '+353', 'flag' => '🇮🇪'],    // アイルランド
    'JP' => ['code' => '+81', 'flag' => '🇯🇵'],     // 日本
    'KR' => ['code' => '+82', 'flag' => '🇰🇷'],     // 韓国
    'SG' => ['code' => '+65', 'flag' => '🇸🇬'],     // シンガポール
    'AU' => ['code' => '+61', 'flag' => '🇦🇺'],     // オーストラリア
    'NZ' => ['code' => '+64', 'flag' => '🇳🇿'],     // ニュージーランド
];
@endphp

<div class="country-select-container {{ $class }}">
    <select name="{{ $name }}" id="{{ $name }}" class="country-select" {{ $required ? 'required' : '' }}>
        <option value="">{{ \App\Services\LanguageService::trans('select_country', $lang) }}</option>
        @foreach($countries as $code => $country)
            <option value="{{ $code }}" 
                    data-country-code="{{ $country['code'] }}"
                    {{ $value === $code ? 'selected' : '' }}>
                {{ $country['flag'] }} {{ \App\Services\LanguageService::trans('country_' . strtolower($code), $lang) }} ({{ $country['code'] }})
            </option>
        @endforeach
    </select>
</div>
