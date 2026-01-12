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
                'ip' => request()->ip()
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
                    // 未ログインユーザーはIP判定を実行
                    // セッションにdetected_languageがある場合は再利用（パフォーマンス向上）
                    // ただし、セッションがない場合は必ずIP判定を実行
                    if (session()->has('detected_language')) {
                        $lang = session('detected_language');
                        // 既存データとの互換性：小文字の場合は大文字に変換
                        if ($lang === 'ja') $lang = 'JA';
                        if ($lang === 'en') $lang = 'EN';
                        
                        // current_languageも更新（互換性のため）
                        if (session('current_language') !== $lang) {
                            session(['current_language' => $lang]);
                        }
                        
                        \Log::info('未ログインユーザー：セッションから検出済み言語を取得（IP判定をスキップ）', [
                            'language' => $lang,
                            'session_id' => session()->getId(),
                            'ip' => request()->ip()
                        ]);
                        
                        return $lang;
                    }
                    
                    // セッションにdetected_languageがない場合はIP判定を実行
                    \Log::info('未ログインユーザー：IP判定を開始（セッションにdetected_languageなし）', [
                        'ip' => request()->ip(),
                        'session_id' => session()->getId(),
                        'has_current_language' => session()->has('current_language'),
                        'current_language' => session('current_language', 'N/A')
                    ]);
                    
                    $language = self::getLanguageFromIp();
                    
                    // セッションに保存（次回以降のAPI呼び出しを回避）
                    try {
                        session(['current_language' => $language]);
                        session(['detected_language' => $language]);
                        \Log::info('未ログインユーザー：言語をセッションに保存', [
                            'language' => $language,
                            'session_id' => session()->getId(),
                            'ip' => request()->ip()
                        ]);
                    } catch (\Exception $e) {
                        \Log::warning('セッション保存に失敗', [
                            'error' => $e->getMessage(),
                            'language' => $language
                        ]);
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
     * IPアドレスから言語を判定（パフォーマンス最適化）
     */
    private static function getLanguageFromIp()
    {
        $ip = request()->ip();
        
        // IPアドレスの取得をログに記録（infoレベルで確実に記録）
        \Log::info('IPアドレスからの言語判定開始', [
            'ip' => $ip,
            'remote_addr' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
            'http_x_forwarded_for' => $_SERVER['HTTP_X_FORWARDED_FOR'] ?? 'N/A',
            'http_client_ip' => $_SERVER['HTTP_CLIENT_IP'] ?? 'N/A',
            'session_id' => session()->getId(),
            'has_detected_language' => session()->has('detected_language'),
            'detected_language' => session('detected_language', 'N/A')
        ]);
        
        // プライベートIPの判定
        $isPrivateIp = empty($ip) || $ip === '127.0.0.1' || $ip === '::1' || 
                       strpos($ip, '192.168.') === 0 || strpos($ip, '10.') === 0 ||
                       strpos($ip, '172.16.') === 0 || strpos($ip, '172.17.') === 0 ||
                       strpos($ip, '172.18.') === 0 || strpos($ip, '172.19.') === 0 ||
                       strpos($ip, '172.20.') === 0 || strpos($ip, '172.21.') === 0 ||
                       strpos($ip, '172.22.') === 0 || strpos($ip, '172.23.') === 0 ||
                       strpos($ip, '172.24.') === 0 || strpos($ip, '172.25.') === 0 ||
                       strpos($ip, '172.26.') === 0 || strpos($ip, '172.27.') === 0 ||
                       strpos($ip, '172.28.') === 0 || strpos($ip, '172.29.') === 0 ||
                       strpos($ip, '172.30.') === 0 || strpos($ip, '172.31.') === 0;
        
        // プライベートIPの場合は、開発環境設定を確認
        if ($isPrivateIp) {
            \Log::info('プライベートIPを検出', [
                'ip' => $ip,
                'note' => '開発環境設定を確認します'
            ]);
            
            // 開発環境で強制的に日本語を返す設定がある場合
            if (env('FORCE_JA_ON_PRIVATE_IP', false)) {
                \Log::info('プライベートIPで強制的に日本語を返す（開発環境設定）', ['ip' => $ip]);
                $language = 'JA';
                session(['detected_language' => $language]);
                return $language;
            }
            
            // プライベートIPの場合は、APIを呼び出しても失敗する可能性が高いため、デフォルト（英語）を返す
            \Log::info('プライベートIPのため、デフォルト（英語）を返す', ['ip' => $ip]);
            return 'EN';
        }
        
        // パブリックIPの場合は、IPアドレスから言語を判定（外部API呼び出し）
        try {
            $countryCode = self::getCountryCodeFromIp($ip);
            $language = ($countryCode === 'JP') ? 'JA' : 'EN'; // 英語コードで返す
            
            \Log::info('IPアドレスから言語を判定成功', [
                'ip' => $ip,
                'countryCode' => $countryCode,
                'language' => $language
            ]);
            
            // セッションに保存（次回以降のAPI呼び出しを回避）
            session(['detected_language' => $language]);
            return $language;
        } catch (\Exception $e) {
            \Log::warning('IPアドレスからの言語判定に失敗', [
                'ip' => $ip,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            
            // エラー時はデフォルト（英語）を返す
            return 'EN';
        }
    }

    /**
     * IPアドレスから国コードを取得
     */
    private static function getCountryCodeFromIp($ip)
    {
        // ip-api.comの無料APIを使用（1分間に45リクエストまで）
        // fieldsパラメータで必要な情報のみ取得（status, message, countryCode）
        $url = "http://ip-api.com/json/{$ip}?fields=status,message,countryCode";
        
        $context = stream_context_create([
            'http' => [
                'timeout' => 3, // 3秒でタイムアウト（少し長めに設定）
                'ignore_errors' => true,
                'user_agent' => 'Mozilla/5.0 (compatible; LanguageService/1.0)',
            ]
        ]);
        
        $response = @file_get_contents($url, false, $context);
        
        if ($response === false) {
            \Log::warning('IP Geolocation APIへの接続に失敗', [
                'ip' => $ip,
                'url' => $url,
                'error' => error_get_last()
            ]);
            throw new \Exception('IP Geolocation APIへの接続に失敗しました');
        }
        
        $data = json_decode($response, true);
        
        // APIレスポンスのエラーチェック
        if (!is_array($data)) {
            \Log::warning('IP Geolocation APIのレスポンスが不正', [
                'ip' => $ip,
                'response' => $response
            ]);
            throw new \Exception('IP Geolocation APIのレスポンスが不正です');
        }
        
        // statusがfailの場合はエラー
        if (isset($data['status']) && $data['status'] === 'fail') {
            $message = $data['message'] ?? '不明なエラー';
            \Log::warning('IP Geolocation APIがエラーを返しました', [
                'ip' => $ip,
                'message' => $message
            ]);
            throw new \Exception("IP Geolocation APIエラー: {$message}");
        }
        
        // countryCodeが存在するか確認
        if (isset($data['countryCode']) && !empty($data['countryCode'])) {
            return $data['countryCode'];
        }
        
        \Log::warning('国コードが取得できませんでした', [
            'ip' => $ip,
            'data' => $data
        ]);
        throw new \Exception('国コードの取得に失敗しました');
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

