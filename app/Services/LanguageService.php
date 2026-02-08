<?php

namespace App\Services;

class LanguageService
{
    /**
     * 翻訳文字列を取得
     * 
     * @param string $key 翻訳キー
     * @param string|null $language 言語コード（JA, EN）
     * @param array $replace 置換パラメータ（例: ['days' => 5, 'coins' => 10]）
     * @return string 翻訳された文字列
     */
    public static function trans($key, $language = null, $replace = [])
    {
        $language = $language ?? self::getCurrentLanguage();
        
        // 英語コード（JA, EN）を小文字に変換（既存の翻訳ファイルとの互換性）
        $langCode = strtolower($language);
        if ($langCode === 'ja') $langCode = 'ja';
        elseif ($langCode === 'en') $langCode = 'en';
        else $langCode = 'ja'; // デフォルト
        
        $translations = self::getTranslations();
        
        $translated = $translations[$langCode][$key] ?? $translations['ja'][$key] ?? $key;
        
        // 置換パラメータがある場合は置換
        if (!empty($replace)) {
            foreach ($replace as $search => $value) {
                $translated = str_replace(":{$search}", $value, $translated);
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
        
        // 英語コード（JA, EN）を小文字に変換（既存の翻訳ファイルとの互換性）
        $langCode = strtolower($language);
        if ($langCode === 'ja') $langCode = 'ja';
        elseif ($langCode === 'en') $langCode = 'en';
        else $langCode = 'ja'; // デフォルト
        
        $tagTranslations = self::getTagTranslations();
        
        return $tagTranslations[$langCode][$tag] ?? $tag;
    }

    /**
     * 有効なタグのリストを取得
     */
    public static function getValidTags()
    {
        $tagTranslations = self::getTagTranslations();
        // 英語の翻訳キーから有効なタグを取得（カテゴリ名を除く）
        $validTags = array_keys($tagTranslations['en']);
        
        // カテゴリ名を除外
        $categories = [
            '生活・日常', '健康・医療', '仕事・キャリア', '学び・教育', 'テクノロジー・デジタル',
            'テクノロジー・ガジェット', '趣味・エンタメ', '旅行・地域', '恋愛・人間関係',
            'お金・法律・制度', '社会・政治・国際', '文化・宗教・歴史', '科学・自然・宇宙',
            'ペット・動物', '植物・ガーデニング', '不思議・オカルト', '雑談・ユーモア',
            'R18・アダルト', 'Q&A・その他'
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
     * 現在のユーザーの言語を取得（キャッシュを使用してパフォーマンス向上）
     */
    public static function getCurrentLanguage()
    {
        try {
            \Log::info('getCurrentLanguage呼び出し', [
                'session_id' => session()->getId(),
                'is_authenticated' => auth()->check(),
                'has_detected_language' => session()->has('detected_language'),
                'detected_language' => session('detected_language', 'N/A'),
                'has_current_language' => session()->has('current_language'),
                'current_language' => session('current_language', 'N/A'),
                'country_code' => self::getCountryCodeFromRequest()
            ]);
            
            $language = 'EN'; // デフォルト（英語コード）
            
            try {
                if (auth()->check()) {
                    $user = auth()->user();
                    if ($user) {
                        // ログインユーザーの場合、ユーザー設定を優先
                        $language = $user->language ?? 'EN';
                        
                        // 既存データとの互換性：小文字の場合は大文字に変換
                        if ($language === 'ja') $language = 'JA';
                        if ($language === 'en') $language = 'EN';
                        
                        \Log::info('ログインユーザーの言語を取得', [
                            'user_id' => $user->user_id,
                            'language' => $language,
                            'user_language_setting' => $user->language
                        ]);
                        
                        // セッションキャッシュと異なる場合は更新
                        if (session('current_language') !== $language) {
                            session(['current_language' => $language]);
                        }
                    }
                } else {
                    // 未ログインユーザー：国コード（CF-IPCountry）を最優先。IPは使わない
                    $countryCode = self::getCountryCodeFromRequest();

                    // 国コードが取得できた場合は常に国コードで言語を決定（セッションより優先）
                    if ($countryCode !== null) {
                        $language = ($countryCode === 'JP') ? 'JA' : 'EN';
                        if (session('current_language') !== $language) {
                            session(['current_language' => $language]);
                        }
                        if (session('detected_language') !== $language) {
                            session(['detected_language' => $language]);
                        }
                        \Log::info('未ログインユーザー：国コードを最優先で言語を決定', [
                            'country_code' => $countryCode,
                            'language' => $language,
                            'session_id' => session()->getId()
                        ]);
                        return $language;
                    }

                    // 国コードが取れない場合のみセッションのキャッシュを使用
                    if (session()->has('detected_language')) {
                        $lang = session('detected_language');
                        if ($lang === 'ja') $lang = 'JA';
                        if ($lang === 'en') $lang = 'EN';
                        if (session('current_language') !== $lang) {
                            session(['current_language' => $lang]);
                        }
                        \Log::info('未ログインユーザー：国コードなしのためセッションの言語を使用', [
                            'language' => $lang,
                            'session_id' => session()->getId()
                        ]);
                        return $lang;
                    }

                    // 国コードもセッションもない場合：開発用フォールバックのみ（IPは使わない）
                    $language = self::getLanguageFromCountryCode();
                    try {
                        session(['current_language' => $language]);
                        session(['detected_language' => $language]);
                    } catch (\Exception $e) {
                        \Log::warning('セッション保存に失敗', ['error' => $e->getMessage(), 'language' => $language]);
                    }
                }
            } catch (\Exception $e) {
                // エラーが発生した場合はデフォルト値を使用
                \Log::warning('getCurrentLanguageでエラーが発生', [
                    'error' => $e->getMessage(),
                    'trace' => $e->getTraceAsString()
                ]);
                $language = 'EN';
            }
            
            return $language;
        } catch (\Exception $e) {
            // すべてのエラーをキャッチしてデフォルト値を返す
            \Log::error('getCurrentLanguageで致命的なエラーが発生', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return 'EN';
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
        // Cloudflare の特殊コードは無視（XX=不明, T1=Tor）
        if ($code === 'XX' || $code === 'T1' || strlen($code) !== 2) {
            return null;
        }
        return $code;
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

        // Cloudflare 経由で有効な国コードがある場合：国コードで判定
        if ($countryCode !== null) {
            $language = ($countryCode === 'JP') ? 'JA' : 'EN';
            \Log::info('国コードから言語を判定成功', [
                'country_code' => $countryCode,
                'language' => $language
            ]);
            session(['detected_language' => $language]);
            return $language;
        }

        // CF-IPCountry がない場合（Cloudflare 未経由・ローカル等）：IPは使わずデフォルト扱い
        // 開発環境でプライベートIPのときのみ FORCE_JA_ON_PRIVATE_IP を参照
        $ip = request()->header('CF-Connecting-IP') ?: (request()->ip() ?? '');
        $isPrivateIp = empty($ip) || $ip === '127.0.0.1' || $ip === '::1' ||
            strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 ||
            (preg_match('/^172\.(1[6-9]|2[0-9]|3[0-1])\./', $ip) === 1);

        if ($isPrivateIp && env('FORCE_JA_ON_PRIVATE_IP', false)) {
            \Log::info('Cloudflare未経由・プライベートIPのため開発環境設定で日本語を返す', ['ip' => $ip]);
            session(['detected_language' => 'JA']);
            return 'JA';
        }

        \Log::info('国コードが取得できないためデフォルト（英語）を返す', ['reason' => 'CF-IPCountryなしまたは無効']);
        return 'EN';
    }

    /**
     * 翻訳文字列の定義（ファイルから読み込む）
     */
    private static function getTranslations()
    {
        static $translations = null;
        
        if ($translations === null) {
            $translations = [];
            $languages = ['ja', 'en'];
            
            foreach ($languages as $lang) {
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
            $languages = ['ja', 'en'];
            
            foreach ($languages as $lang) {
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
}

