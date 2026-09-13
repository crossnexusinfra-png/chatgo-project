<?php

namespace App\Services;

use Illuminate\Support\Facades\URL;

class LanguageService
{
    private static ?string $requestLocale = null;

    /**
     * URL に使うロケール一覧（小文字）。言語追加は config のみ。
     *
     * @return list<string>
     */
    public static function supportedLocales(): array
    {
        $locales = config('localization.supported', ['ja', 'en']);
        if (! is_array($locales)) {
            return ['ja', 'en'];
        }

        $normalized = [];
        foreach ($locales as $locale) {
            if (! is_string($locale) || $locale === '') {
                continue;
            }
            $normalized[] = strtolower($locale);
        }

        return $normalized !== [] ? array_values(array_unique($normalized)) : ['ja', 'en'];
    }

    public static function fallbackLocale(): string
    {
        $fallback = strtolower((string) config('localization.fallback', 'en'));

        return in_array($fallback, self::supportedLocales(), true) ? $fallback : (self::supportedLocales()[0] ?? 'en');
    }

    public static function isSupported(string $locale): bool
    {
        return in_array(strtolower($locale), self::supportedLocales(), true);
    }

    /**
     * DB / 既存コードの JA, EN など。
     *
     * @return list<string>
     */
    public static function appLanguages(): array
    {
        return array_map('strtoupper', self::supportedLocales());
    }

    public static function toUrlLocale(?string $language): string
    {
        if ($language === null || $language === '') {
            return self::fallbackLocale();
        }

        $normalized = strtolower($language);
        if (self::isSupported($normalized)) {
            return $normalized;
        }

        return self::fallbackLocale();
    }

    public static function toAppLanguage(?string $language): string
    {
        return strtoupper(self::toUrlLocale($language));
    }

    public static function htmlLang(?string $language): string
    {
        return self::toUrlLocale($language);
    }

    public static function clearRequestLocale(): void
    {
        self::$requestLocale = null;
    }

    /**
     * UI の URL ロケールをこのリクエストの正とする。
     */
    public static function applyRequestLocale(string $locale, bool $persistSession = true): void
    {
        $locale = self::toUrlLocale($locale);
        self::$requestLocale = $locale;
        app()->setLocale($locale);
        URL::defaults(['locale' => $locale]);

        if (! $persistSession) {
            return;
        }

        $appLanguage = self::toAppLanguage($locale);
        try {
            if (session('current_language') !== $appLanguage) {
                session(['current_language' => $appLanguage]);
            }
            if (session('detected_language') !== $appLanguage) {
                session(['detected_language' => $appLanguage]);
            }
        } catch (\Throwable $e) {
            \Log::warning('ロケールのセッション保存に失敗', [
                'error' => $e->getMessage(),
                'locale' => $locale,
            ]);
        }
    }

    /**
     * locale なしエンドポイント向け。route() が {locale} を要求しても落ちないようにする。
     */
    public static function applyUrlDefaults(): void
    {
        $locale = self::preferredUrlLocaleFromSessionOrDetect();
        URL::defaults(['locale' => $locale]);
        app()->setLocale($locale);
    }

    public static function preferredUrlLocale(): string
    {
        return self::toUrlLocale(self::detectPreferredAppLanguage());
    }

    /**
     * 翻訳文字列を取得
     *
     * @param  string  $key  翻訳キー
     * @param  string|null  $language  言語コード（JA, EN または ja, en）
     * @param  array  $replace  置換パラメータ（例: ['days' => 5, 'coins' => 10]）
     * @return string 翻訳された文字列
     */
    public static function trans($key, $language = null, $replace = [])
    {
        $language = $language ?? self::getCurrentLanguage();
        $langCode = self::toUrlLocale($language);
        $fallback = self::fallbackLocale();

        $translations = self::getTranslations();

        $translated = $translations[$langCode][$key]
            ?? $translations[$fallback][$key]
            ?? $translations['ja'][$key]
            ?? $key;

        if (! empty($replace)) {
            foreach ($replace as $search => $value) {
                $valueStr = $value instanceof \Stringable || is_scalar($value)
                    ? (string) $value
                    : '';
                $translated = str_replace(":{$search}", $valueStr, $translated);
                $translated = str_replace('{'.$search.'}', $valueStr, $translated);
            }
        }

        return $translated;
    }

    /**
     * タグの翻訳を取得
     */
    public static function transTag($tag, $language = null)
    {
        $language = $language ?? self::getCurrentLanguage();
        $langCode = self::toUrlLocale($language);

        $tagTranslations = self::getTagTranslations();

        return $tagTranslations[$langCode][$tag] ?? $tag;
    }

    /**
     * 有効なタグのリストを取得
     */
    public static function getValidTags()
    {
        $tagTranslations = self::getTagTranslations();
        $keyLocale = self::toUrlLocale((string) config('localization.tag_key_locale', 'en'));
        $source = $tagTranslations[$keyLocale] ?? $tagTranslations[self::fallbackLocale()] ?? [];
        $validTags = array_keys($source);

        $categories = [
            '生活・日常', '健康・医療', '仕事・キャリア', '学び・教育', 'テクノロジー・デジタル',
            'テクノロジー・ガジェット', '趣味・エンタメ', '旅行・地域', '恋愛・人間関係',
            'お金・法律・制度', '社会・政治・国際', '文化・宗教・歴史', '科学・自然・宇宙',
            'ペット・動物', '植物・ガーデニング', '不思議・オカルト', '雑談・ユーモア',
            'R18・アダルト', 'Q&A・その他',
        ];

        return array_diff($validTags, $categories);
    }

    /**
     * タグが有効かどうかをチェック
     */
    public static function isValidTag($tag)
    {
        $validTags = self::getValidTags();

        return in_array($tag, $validTags);
    }

    /**
     * 現在の表示言語を取得（キャッシュを使用してパフォーマンス向上）
     */
    public static function getCurrentLanguage()
    {
        try {
            if (self::$requestLocale !== null) {
                return self::toAppLanguage(self::$requestLocale);
            }

            $sessionLanguage = self::validAppLanguageFromSession('current_language');
            if ($sessionLanguage !== null) {
                return $sessionLanguage;
            }

            return self::detectPreferredAppLanguage();
        } catch (\Exception $e) {
            \Log::error('getCurrentLanguageで致命的なエラーが発生', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::toAppLanguage(self::fallbackLocale());
        }
    }

    /**
     * / や旧 URL からの入場時に使うロケール（URL が無いときの推定）。
     */
    public static function detectPreferredAppLanguage(): string
    {
        try {
            \Log::info('getCurrentLanguage呼び出し', [
                'session_id' => session()->getId(),
                'is_authenticated' => auth()->check(),
                'has_detected_language' => session()->has('detected_language'),
                'detected_language' => session('detected_language', 'N/A'),
                'has_current_language' => session()->has('current_language'),
                'current_language' => session('current_language', 'N/A'),
                'country_code' => self::getCountryCodeFromRequest(),
            ]);

            $language = self::toAppLanguage(self::fallbackLocale());

            try {
                if (auth()->check()) {
                    $user = auth()->user();
                    if ($user) {
                        $language = self::toAppLanguage($user->language ?? self::fallbackLocale());

                        \Log::info('ログインユーザーの言語を取得', [
                            'user_id' => $user->user_id,
                            'language' => $language,
                            'user_language_setting' => $user->language,
                        ]);

                        if (session('current_language') !== $language) {
                            session(['current_language' => $language]);
                        }
                    }
                } else {
                    $countryCode = self::getCountryCodeFromRequest();

                    if ($countryCode !== null) {
                        $language = self::toAppLanguage(self::localeFromCountryCode($countryCode));
                        if (session('current_language') !== $language) {
                            session(['current_language' => $language]);
                        }
                        if (session('detected_language') !== $language) {
                            session(['detected_language' => $language]);
                        }
                        \Log::info('未ログインユーザー：国コードを最優先で言語を決定', [
                            'country_code' => $countryCode,
                            'language' => $language,
                            'session_id' => session()->getId(),
                        ]);

                        return $language;
                    }

                    $sessionDetected = self::validAppLanguageFromSession('detected_language');
                    if ($sessionDetected !== null) {
                        if (session('current_language') !== $sessionDetected) {
                            session(['current_language' => $sessionDetected]);
                        }
                        \Log::info('未ログインユーザー：国コードなしのためセッションの言語を使用', [
                            'language' => $sessionDetected,
                            'session_id' => session()->getId(),
                        ]);

                        return $sessionDetected;
                    }

                    $language = self::getLanguageFromCountryCode();
                    try {
                        session(['current_language' => $language]);
                        session(['detected_language' => $language]);
                    } catch (\Exception $e) {
                        \Log::warning('セッション保存に失敗', ['error' => $e->getMessage(), 'language' => $language]);
                    }
                }
            } catch (\Exception $e) {
                \Log::warning('getCurrentLanguageでエラーが発生', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString(),
                ]);
                $language = self::toAppLanguage(self::fallbackLocale());
            }

            return $language;
        } catch (\Exception $e) {
            \Log::error('getCurrentLanguageで致命的なエラーが発生', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return self::toAppLanguage(self::fallbackLocale());
        }
    }

    /**
     * Cloudflare の CF-IPCountry から国コードを取得（信頼できる国コードのみ使用）
     * XX=不明, T1=Tor の場合は null を返す
     * サーバーによっては HTTP ヘッダが $_SERVER['HTTP_CF_IPCOUNTRY'] で渡るため両方参照する
     */
    private static function getCountryCodeFromRequest()
    {
        $req = request();
        $code = $req->header('CF-IPCountry');
        if ($code === null || $code === '') {
            $code = $req->server('HTTP_CF_IPCOUNTRY');
        }
        if ($code === null || $code === '') {
            return null;
        }
        $code = strtoupper(trim((string) $code));
        if ($code === 'XX' || $code === 'T1' || strlen($code) !== 2) {
            return null;
        }

        return $code;
    }

    private static function localeFromCountryCode(string $countryCode): string
    {
        $map = config('localization.country_locales', ['JP' => 'ja']);
        if (! is_array($map)) {
            $map = ['JP' => 'ja'];
        }

        $countryCode = strtoupper($countryCode);
        $locale = $map[$countryCode] ?? self::fallbackLocale();

        return self::toUrlLocale(is_string($locale) ? $locale : self::fallbackLocale());
    }

    /**
     * 国コード（Cloudflare CF-IPCountry）から言語を判定
     * IPは信頼しないため外部APIは使わず、CF-IPCountry のみ使用
     */
    private static function getLanguageFromCountryCode()
    {
        $countryCode = self::getCountryCodeFromRequest();
        $req = request();
        $rawHeader = $req->header('CF-IPCountry');
        $rawServer = $req->server('HTTP_CF_IPCOUNTRY');

        \Log::info('国コードからの言語判定', [
            'country_code' => $countryCode ?? 'N/A',
            'raw_header_CF_IPCountry' => $rawHeader !== null ? $rawHeader : '(null)',
            'raw_server_HTTP_CF_IPCOUNTRY' => $rawServer !== null ? $rawServer : '(null)',
            'session_id' => session()->getId(),
            'has_detected_language' => session()->has('detected_language'),
        ]);

        if ($countryCode !== null) {
            $language = self::toAppLanguage(self::localeFromCountryCode($countryCode));
            \Log::info('国コードから言語を判定成功', [
                'country_code' => $countryCode,
                'language' => $language,
            ]);
            session(['detected_language' => $language]);

            return $language;
        }

        $ip = request()->ip() ?? '';
        $isPrivateIp = empty($ip) || $ip === '127.0.0.1' || $ip === '::1' ||
            strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 ||
            (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) === 1);

        if ($isPrivateIp && env('FORCE_JA_ON_PRIVATE_IP', false)) {
            \Log::info('Cloudflare未経由・プライベートIPのため開発環境設定で日本語を返す', ['ip' => $ip]);
            session(['detected_language' => 'JA']);

            return 'JA';
        }

        \Log::info('国コードが取得できないためデフォルト（英語）を返す', ['reason' => 'CF-IPCountryなしまたは無効']);

        return self::toAppLanguage(self::fallbackLocale());
    }

    private static function preferredUrlLocaleFromSessionOrDetect(): string
    {
        $sessionLanguage = self::validAppLanguageFromSession('current_language')
            ?? self::validAppLanguageFromSession('detected_language');
        if ($sessionLanguage !== null) {
            return self::toUrlLocale($sessionLanguage);
        }

        return self::preferredUrlLocale();
    }

    private static function validAppLanguageFromSession(string $key): ?string
    {
        if (! session()->has($key)) {
            return null;
        }

        $value = session($key);
        if (! is_string($value) || $value === '') {
            return null;
        }

        $locale = strtolower($value);
        if (! self::isSupported($locale)) {
            return null;
        }

        return self::toAppLanguage($locale);
    }

    /**
     * 翻訳文字列の定義（ファイルから読み込む）
     */
    private static function getTranslations()
    {
        static $translations = null;

        if ($translations === null) {
            $translations = [];
            foreach (self::supportedLocales() as $lang) {
                $filePath = resource_path("lang/{$lang}.php");
                if (file_exists($filePath)) {
                    $translations[$lang] = require $filePath;
                } else {
                    $translations[$lang] = [];
                }
            }
        }

        return $translations;
    }

    /**
     * タグの翻訳定義（ファイルから読み込む）
     */
    private static function getTagTranslations()
    {
        static $tagTranslations = null;

        if ($tagTranslations === null) {
            $tagTranslations = [];
            foreach (self::supportedLocales() as $lang) {
                $filePath = resource_path("lang/tags/{$lang}.php");
                if (file_exists($filePath)) {
                    $tagTranslations[$lang] = require $filePath;
                } else {
                    $tagTranslations[$lang] = [];
                }
            }
        }

        return $tagTranslations;
    }

    public static function unprefixedUiPattern(): string
    {
        $prefixes = config('localization.unprefixed_ui_prefixes', []);
        if (! is_array($prefixes) || $prefixes === []) {
            return 'threads(?:/.*)?';
        }

        $safe = [];
        foreach ($prefixes as $prefix) {
            if (! is_string($prefix) || $prefix === '') {
                continue;
            }
            $safe[] = preg_quote($prefix, '#');
        }

        if ($safe === []) {
            return 'threads(?:/.*)?';
        }

        return '(?:'.implode('|', $safe).')(?:/.*)?';
    }
}
