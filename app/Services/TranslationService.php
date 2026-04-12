<?php

namespace App\Services;

use App\Models\TranslationCache;
use Illuminate\Support\Facades\Log;

/**
 * GPT-4o mini を用いたルーム名・リプライ本文の翻訳サービス
 *
 * - ルーム名: 投稿時に保存した translation_caches の行は無期限で利用（1年経過でも再翻訳しない）。
 * - リプライ: 保存から TranslationCache::REPLY_TRANSLATION_TTL_YEARS 年でキャッシュ無効となり、表示時に再翻訳し得る。
 * - ルーム名の表示: 一覧・通知等はキャッシュのみ（$allowLiveTranslation=false）。スレッド詳細は未キャッシュ時にフロントが POST で translateThreadTitleLiveForUi（リプライの deferred 翻訳と同様の試行上限・失敗区分）。
 * - 元言語（source_lang）は「表示言語と比較して翻訳が必要か」の判別にのみ利用する。APIには送らない。
 * - 元言語は送信時の表示言語（threads.source_lang / responses.source_lang）を優先。送信者削除後も正しく判定可能。
 * - 返信の translateReply では、返信元・返信本文ともに「返信先（子）リプライの source_lang」のテキストを渡す（親が別言語なら getParentBodyForReplyTranslationContext で揃える）。
 * - API が trim・空白正規化後に原文と同一文字列を返した場合: 元言語 EN はそのまま、JA は ext-intl の Transliterator でラテン表記に差し替え（未導入・失敗時は同一のまま保存）。
 */
class TranslationService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL = 'gpt-4o-mini';

    /** 送信 JSON がこれを超える場合はリプライ本文過大など利用者側要因としてリトライ不可扱い（目安。モデル上限より余裕を見る） */
    private const OPENAI_CHAT_COMPLETION_MAX_JSON_BYTES = 450000;

    /**
     * ライブ翻訳 API：同一リプライ（ユーザーまたは IP × response_id）あたり 1 日最大試行回数（滑動 24 時間）。
     * 成功・失敗どちらも 1 回としてカウント。スパム用の分間制限はライブ翻訳 POST の throttle:api（60/分・ユーザー、100/分・IP）に任せる。
     */
    public const LIVE_TRANSLATE_PER_RESPONSE_MAX_ATTEMPTS = 5;

    /** 上記のウィンドウ長（秒）。= 1 日相当の滑動窓。 */
    public const LIVE_TRANSLATE_PER_RESPONSE_DECAY_SECONDS = 86400;

    /** 翻訳不可（APIキー未設定・セキュア HTTP の設定不備など）。利用者には管理者対応待ちの文言を出す。 */
    public const TRANSLATION_UI_TIER_ADMIN_REQUIRED = 'admin_required';

    /** しばらく待てば再試行しうる（通信失敗・429/5xx・ルート throttle:api 等）。 */
    public const TRANSLATION_UI_TIER_RETRY_LATER = 'retry_later';

    /** 再試行しても同様の失敗が予想される（4xx 構造エラー・200 だが本文不正など）。 */
    public const TRANSLATION_UI_TIER_NO_RETRY = 'no_retry';

    /**
     * APIキーを取得
     */
    private static function getApiKey(): string
    {
        return (string) config('services.openai.api_key', '');
    }

    /**
     * 翻訳が必要か（表示言語と元言語を比較。異なる場合のみ翻訳する）
     * 元言語はAPIには送らず、この判別にのみ利用する。
     */
    public static function shouldTranslate(string $sourceLang, string $targetLang): bool
    {
        $s = strtoupper($sourceLang);
        $t = strtoupper($targetLang);
        return $s !== $t && !empty(trim($s)) && !empty(trim($t));
    }

    /**
     * プロンプト用の言語名を取得（Japanese / English）
     */
    private static function langNameForPrompt(string $lang): string
    {
        $lang = strtoupper($lang);
        return $lang === 'JA' ? 'Japanese' : 'English';
    }

    /**
     * 言語コードを正規化（ja/en → JA/EN）
     */
    public static function normalizeLang(string $lang): string
    {
        $lang = strtoupper(trim($lang));
        return in_array($lang, ['JA', 'EN'], true) ? $lang : 'EN';
    }

    /**
     * API 戻り値と原文が「同一」とみなす比較用（trim・連続空白の圧縮）
     */
    private static function normalizeTextForIdenticalCompare(string $s): string
    {
        $s = trim($s);
        $s = preg_replace('/\s+/u', ' ', $s);

        return $s;
    }

    /**
     * 表示用テキストが原文と実質的に異なるか（原文表示トグル用）
     */
    public static function translatedDisplayDiffersFromOriginal(string $display, string $original): bool
    {
        $d = trim((string) $display);
        $o = trim((string) $original);
        if ($d === '' || $o === '') {
            return false;
        }

        return self::normalizeTextForIdenticalCompare($d) !== self::normalizeTextForIdenticalCompare($o);
    }

    /**
     * 翻訳 API が正規化後も原文と同じ文字列を返したときの表示用フォールバック。
     * - 元言語 EN: そのまま
     * - 元言語 JA: ICU でラテン表記（ローマ字系）へ（失敗時は $out のまま）
     */
    private static function resolveIdenticalApiTranslationOutput(string $body, string $out, string $sourceLang): string
    {
        $sourceLang = self::normalizeLang($sourceLang);
        if (self::normalizeTextForIdenticalCompare($out) !== self::normalizeTextForIdenticalCompare($body)) {
            return $out;
        }
        if ($sourceLang === 'EN') {
            return $out;
        }
        if ($sourceLang === 'JA') {
            $latin = self::japaneseBodyToLatinRomaji($body);

            return trim($latin) !== '' ? trim($latin) : $out;
        }

        return $out;
    }

    /**
     * 日本語本文をラテン文字表記へ（intl Transliterator。未利用可能時は原文を返す）
     */
    private static function japaneseBodyToLatinRomaji(string $text): string
    {
        if (trim($text) === '') {
            return $text;
        }
        if (! class_exists(\Transliterator::class)) {
            Log::debug('TranslationService: ext-intl Transliterator not available; romaji fallback skipped');

            return $text;
        }

        $rules = [
            'Japanese-Latin; Latin-ASCII',
            'Any-Latin; Latin-ASCII',
        ];

        foreach ($rules as $id) {
            $tr = \Transliterator::create($id);
            if ($tr === null) {
                continue;
            }
            try {
                $converted = $tr->transliterate($text);
            } catch (\Throwable $e) {
                Log::debug('TranslationService: transliteration failed', ['id' => $id, 'message' => $e->getMessage()]);

                continue;
            }
            if (is_string($converted) && trim($converted) !== '') {
                return trim($converted);
            }
        }

        return $text;
    }

    /**
     * 返信元無し：API翻訳依頼文（{target_language} / {original_text} を置換して送信）
     */
    public static function translateStandalone(string $originalText, string $targetLang): string
    {
        $originalText = trim($originalText);
        if ($originalText === '') {
            return '';
        }

        $raw = self::translateStandaloneRaw($originalText, $targetLang);

        return $raw !== null ? $raw : $originalText;
    }

    /**
     * 単体翻訳の API 呼び出し。失敗時は null（原文フォールバックは呼び出し側）
     */
    private static function translateStandaloneRaw(string $originalText, string $targetLang): ?string
    {
        $meta = self::translateStandaloneRawWithMeta($originalText, $targetLang);

        return $meta['content'];
    }

    /**
     * @return array{content: ?string, translation_ui_tier: ?string, translation_user_message_key: ?string}
     */
    private static function translateStandaloneRawWithMeta(string $originalText, string $targetLang): array
    {
        $originalText = trim($originalText);
        if ($originalText === '') {
            return ['content' => '', 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        $targetLanguage = self::langNameForPrompt($targetLang);
        $prompt = "Translate the following text into {target_language}.\n\n"
            . "Rules:\n"
            . "- Do not translate URLs, code, or emojis.\n"
            . "- Keep proper nouns and brand names accurate.\n"
            . "- Preserve slang meaning naturally.\n"
            . "- Keep formatting unchanged.\n"
            . "- Return only the translated text.\n"
            . "- If the input is unclear, nonsensical, or cannot be confidently translated, return it exactly as-is.\n"
            . "- Never output explanations or meta text.\n"
            . "- Output must always be either a natural translation or the original text.\n\n"
            . "Text:\n"
            . "\"\"\"\n"
            . "{original_text}\n"
            . "\"\"\"";

        $prompt = str_replace(
            ['{target_language}', '{original_text}'],
            [$targetLanguage, $originalText],
            $prompt
        );

        $r = self::callChatCompletionResult($prompt);
        if ($r['content'] !== null) {
            return ['content' => $r['content'], 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        return [
            'content' => null,
            'translation_ui_tier' => $r['translation_ui_tier'] ?? self::TRANSLATION_UI_TIER_NO_RETRY,
            'translation_user_message_key' => $r['translation_user_message_key'] ?? null,
        ];
    }

    /**
     * 返信元あり：API翻訳依頼文（{target_language} / {parent_text} / {reply_text} を置換して送信）
     */
    public static function translateReply(string $replyText, string $parentText, string $targetLang): string
    {
        $replyText = trim($replyText);
        if ($replyText === '') {
            return '';
        }
        $parentText = trim($parentText);

        $raw = self::translateReplyRaw($replyText, $parentText, $targetLang);

        return $raw !== null ? $raw : $replyText;
    }

    /**
     * 返信文脈付き翻訳の API 呼び出し。失敗時は null
     */
    private static function translateReplyRaw(string $replyText, string $parentText, string $targetLang): ?string
    {
        $meta = self::translateReplyRawWithMeta($replyText, $parentText, $targetLang);

        return $meta['content'];
    }

    /**
     * @return array{content: ?string, translation_ui_tier: ?string, translation_user_message_key: ?string}
     */
    private static function translateReplyRawWithMeta(string $replyText, string $parentText, string $targetLang): array
    {
        $replyText = trim($replyText);
        if ($replyText === '') {
            return ['content' => '', 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }
        $parentText = trim($parentText);

        $targetLanguage = self::langNameForPrompt($targetLang);
        $prompt = "Translate the reply into {target_language} considering the context.\n\n"
            . "Rules:\n"
            . "- Do not translate URLs, code, or emojis.\n"
            . "- Keep proper nouns and brand names accurate.\n"
            . "- Preserve slang meaning naturally.\n"
            . "- Keep formatting unchanged.\n"
            . "- Return only the translated reply.\n"
            . "- If the input is unclear, nonsensical, or cannot be confidently translated, return it exactly as-is.\n"
            . "- Never output explanations or meta text.\n"
            . "- Output must always be either a natural translation or the original text.\n\n"
            . "Original post:\n"
            . "\"\"\"\n"
            . "{parent_text}\n"
            . "\"\"\"\n\n"
            . "Reply:\n"
            . "\"\"\"\n"
            . "{reply_text}\n"
            . "\"\"\"";

        $prompt = str_replace(
            ['{target_language}', '{parent_text}', '{reply_text}'],
            [$targetLanguage, $parentText, $replyText],
            $prompt
        );

        $r = self::callChatCompletionResult($prompt);
        if ($r['content'] !== null) {
            return ['content' => $r['content'], 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        return [
            'content' => null,
            'translation_ui_tier' => $r['translation_ui_tier'] ?? self::TRANSLATION_UI_TIER_NO_RETRY,
            'translation_user_message_key' => $r['translation_user_message_key'] ?? null,
        ];
    }

    /**
     * 返信元本文を「子リプライの元言語」に揃える（translateReply に渡す親テキスト用）。
     * 親・子の source_lang が同じなら原文のまま。異なれば getTranslatedResponseBody と同じ規則（キャッシュ・1年失効・同言語はAPIなし）で親のみを子の言語へ翻訳する。
     */
    public static function getParentBodyForReplyTranslationContext(
        int $parentResponseId,
        string $parentBody,
        string $parentSourceLang,
        string $childSourceLang,
        bool $allowLiveTranslation = true
    ): string {
        $parentBody = trim($parentBody);
        if ($parentBody === '') {
            return '';
        }
        $parentSourceLang = self::normalizeLang($parentSourceLang);
        $childSourceLang = self::normalizeLang($childSourceLang);
        if ($parentSourceLang === $childSourceLang) {
            return $parentBody;
        }

        return self::getTranslatedResponseBody(
            $parentResponseId,
            $parentBody,
            $childSourceLang,
            null,
            $parentSourceLang,
            $allowLiveTranslation
        );
    }

    /**
     * ルーム作成時に、表示言語と異なる言語へ翻訳してキャッシュに保存する
     * （一覧表示で言語切り替え時に利用）
     */
    public static function translateAndCacheThreadTitleAtCreate(int $threadId, string $title, string $sourceLang): void
    {
        $title = trim($title);
        $sourceLang = self::normalizeLang($sourceLang);
        if ($title === '') {
            return;
        }
        $targetLang = $sourceLang === 'JA' ? 'EN' : 'JA';
        if (!self::shouldTranslate($sourceLang, $targetLang)) {
            return;
        }
        $translated = self::translateStandalone($title, $targetLang);
        if ($translated === $title) {
            return;
        }
        TranslationCache::updateOrCreate(
            [
                'thread_id' => $threadId,
                'target_lang' => $targetLang,
            ],
            [
                'response_id' => null,
                'source_lang' => $sourceLang,
                'translated_text' => $translated,
                'translated_at' => now(),
            ]
        );
    }

    /**
     * スレッドコレクションに表示言語に合わせた display_title を付与する（一覧表示用）
     */
    public static function applyTranslatedThreadTitlesToCollection($threads, string $targetLang): void
    {
        if ($threads === null || $threads->isEmpty()) {
            return;
        }
        foreach ($threads as $thread) {
            $thread->display_title = self::getTranslatedThreadTitle(
                (int) $thread->thread_id,
                $thread->getCleanTitle(),
                $targetLang,
                $thread->source_lang ?? 'EN',
                false
            );
        }
    }

    /**
     * ルーム名の表示用テキストを取得（DBキャッシュは無期限。$allowLiveTranslation が false なら未キャッシュ時はAPIを呼ばず原文を返す）
     *
     * @param int $threadId スレッドID
     * @param string $title 元のルーム名（getCleanTitle 済み推奨）
     * @param string $targetLang 表示言語（JA / EN）
     * @param string $sourceLang 送信時の表示言語（threads.source_lang。翻訳要否の判別にのみ使用）
     * @param bool $allowLiveTranslation キャッシュ未ヒット時にOpenAIで翻訳して保存するか
     * @return string 表示用テキスト
     */
    public static function getTranslatedThreadTitle(int $threadId, string $title, string $targetLang, string $sourceLang, bool $allowLiveTranslation = true): string
    {
        $title = trim($title);
        $targetLang = self::normalizeLang($targetLang);
        $sourceLang = self::normalizeLang($sourceLang);
        if ($title === '') {
            return '';
        }

        $cached = TranslationCache::where('thread_id', $threadId)
            ->whereNull('response_id')
            ->where('target_lang', $targetLang)
            ->first();

        if ($cached && $cached->isValid()) {
            return $cached->translated_text;
        }

        if (!self::shouldTranslate($sourceLang, $targetLang)) {
            return $title;
        }

        if (!$allowLiveTranslation) {
            return $title;
        }

        $meta = self::translateStandaloneRawWithMeta($title, $targetLang);
        $raw = $meta['content'];
        if ($raw === null) {
            return $title;
        }

        $out = trim((string) $raw);
        if ($out === '') {
            return $title;
        }

        $out = self::resolveIdenticalApiTranslationOutput($title, $out, $sourceLang);

        TranslationCache::updateOrCreate(
            [
                'thread_id' => $threadId,
                'target_lang' => $targetLang,
            ],
            [
                'response_id' => null,
                'source_lang' => $sourceLang,
                'translated_text' => $out,
                'translated_at' => now(),
            ]
        );

        return $out;
    }

    /**
     * リプライ本文の表示用テキストを取得（DBキャッシュ優先。キャッシュは1年で失効し得る。$allowLiveTranslation が false なら未キャッシュ・失効時はAPIを呼ばず原文を返す）
     *
     * @param int $responseId レスポンスID
     * @param string $body 元の本文
     * @param string $targetLang 表示言語（JA / EN）
     * @param string|null $parentBody 親リプライ本文（返信の場合）。translateReply 用。親・子とも source_lang と同じ言語のテキストであること（別言語の親は getParentBodyForReplyTranslationContext で揃える）
     * @param string $sourceLang 送信時の表示言語（responses.source_lang。翻訳要否の判別にのみ使用）
     * @param bool $allowLiveTranslation キャッシュ未ヒット時にOpenAIで翻訳して保存するか
     * @return string 表示用テキスト
     */
    public static function getTranslatedResponseBody(int $responseId, string $body, string $targetLang, ?string $parentBody, string $sourceLang, bool $allowLiveTranslation = true): string
    {
        $body = trim($body);
        $targetLang = self::normalizeLang($targetLang);
        $sourceLang = self::normalizeLang($sourceLang);
        if ($body === '') {
            return '';
        }

        $cached = TranslationCache::where('response_id', $responseId)
            ->where('target_lang', $targetLang)
            ->first();

        if ($cached && $cached->isValid()) {
            return $cached->translated_text;
        }

        if (!self::shouldTranslate($sourceLang, $targetLang)) {
            return $body;
        }

        if (!$allowLiveTranslation) {
            return $body;
        }

        $translated = $parentBody !== null && trim($parentBody) !== ''
            ? self::translateReply($body, $parentBody, $targetLang)
            : self::translateStandalone($body, $targetLang);

        $out = trim((string) $translated);
        if ($out === '') {
            $out = $body;
        }

        $out = self::resolveIdenticalApiTranslationOutput($body, $out, $sourceLang);

        // 原文と同一でも保存する。API 失敗・レート制限で原文フォールバックした場合に未保存だと
        // isValid なキャッシュが永遠に無く、毎リクエスト API が走り続ける。
        TranslationCache::updateOrCreate(
            [
                'response_id' => $responseId,
                'target_lang' => $targetLang,
            ],
            [
                'thread_id' => null,
                'source_lang' => $sourceLang,
                'translated_text' => $out,
                'translated_at' => now(),
            ]
        );

        return $out;
    }

    /**
     * チャット画面用：1 リプライをライブ翻訳しキャッシュする。API 失敗時は DB に保存せず success=false。
     *
     * @return array{success: bool, display_text: string, has_translation: bool, error: ?string, translation_ui_tier: ?string, translation_user_message_key: ?string}
     */
    public static function translateResponseBodyLiveForUi(
        int $responseId,
        string $body,
        string $targetLang,
        ?string $parentBodyForApi,
        string $sourceLang
    ): array {
        $body = trim($body);
        $targetLang = self::normalizeLang($targetLang);
        $sourceLang = self::normalizeLang($sourceLang);
        if ($body === '') {
            return ['success' => true, 'display_text' => '', 'has_translation' => false, 'error' => null, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        $cached = TranslationCache::where('response_id', $responseId)
            ->where('target_lang', $targetLang)
            ->first();

        if ($cached && $cached->isValid()) {
            $t = $cached->translated_text;
            $has = trim((string) $t) !== ''
                && self::normalizeTextForIdenticalCompare((string) $t) !== self::normalizeTextForIdenticalCompare($body);

            return ['success' => true, 'display_text' => $t, 'has_translation' => $has, 'error' => null, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        if (! self::shouldTranslate($sourceLang, $targetLang)) {
            return ['success' => true, 'display_text' => $body, 'has_translation' => false, 'error' => null, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        $meta = $parentBodyForApi !== null && trim($parentBodyForApi) !== ''
            ? self::translateReplyRawWithMeta($body, $parentBodyForApi, $targetLang)
            : self::translateStandaloneRawWithMeta($body, $targetLang);

        $raw = $meta['content'];
        $failTier = $meta['translation_ui_tier'];
        $userMsgKey = $meta['translation_user_message_key'] ?? null;

        if ($raw === null) {
            return [
                'success' => false,
                'display_text' => $body,
                'has_translation' => false,
                'error' => 'translation_api_failed',
                'translation_ui_tier' => $failTier ?? self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => $userMsgKey,
            ];
        }

        $out = trim((string) $raw);
        if ($out === '') {
            return [
                'success' => false,
                'display_text' => $body,
                'has_translation' => false,
                'error' => 'translation_api_failed',
                'translation_ui_tier' => self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => null,
            ];
        }

        $out = self::resolveIdenticalApiTranslationOutput($body, $out, $sourceLang);

        TranslationCache::updateOrCreate(
            [
                'response_id' => $responseId,
                'target_lang' => $targetLang,
            ],
            [
                'thread_id' => null,
                'source_lang' => $sourceLang,
                'translated_text' => $out,
                'translated_at' => now(),
            ]
        );

        $hasTranslation = self::normalizeTextForIdenticalCompare($out) !== self::normalizeTextForIdenticalCompare($body);

        return ['success' => true, 'display_text' => $out, 'has_translation' => $hasTranslation, 'error' => null, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
    }

    /**
     * チャット画面用：ルーム名をライブ翻訳しキャッシュする。API 失敗時は DB に保存せず success=false。
     *
     * @return array{success: bool, display_text: string, has_translation: bool, error: ?string, translation_ui_tier: ?string, translation_user_message_key: ?string}
     */
    public static function translateThreadTitleLiveForUi(
        int $threadId,
        string $title,
        string $targetLang,
        string $sourceLang
    ): array {
        $title = trim($title);
        $targetLang = self::normalizeLang($targetLang);
        $sourceLang = self::normalizeLang($sourceLang);
        if ($title === '') {
            return ['success' => true, 'display_text' => '', 'has_translation' => false, 'error' => null, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        $cached = TranslationCache::where('thread_id', $threadId)
            ->whereNull('response_id')
            ->where('target_lang', $targetLang)
            ->first();

        if ($cached && $cached->isValid()) {
            $t = $cached->translated_text;
            $has = self::translatedDisplayDiffersFromOriginal((string) $t, $title);

            return ['success' => true, 'display_text' => $t, 'has_translation' => $has, 'error' => null, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        if (! self::shouldTranslate($sourceLang, $targetLang)) {
            return ['success' => true, 'display_text' => $title, 'has_translation' => false, 'error' => null, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
        }

        $meta = self::translateStandaloneRawWithMeta($title, $targetLang);
        $raw = $meta['content'];
        $failTier = $meta['translation_ui_tier'];
        $userMsgKey = $meta['translation_user_message_key'] ?? null;

        if ($raw === null) {
            return [
                'success' => false,
                'display_text' => $title,
                'has_translation' => false,
                'error' => 'translation_api_failed',
                'translation_ui_tier' => $failTier ?? self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => $userMsgKey,
            ];
        }

        $out = trim((string) $raw);
        if ($out === '') {
            return [
                'success' => false,
                'display_text' => $title,
                'has_translation' => false,
                'error' => 'translation_api_failed',
                'translation_ui_tier' => self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => null,
            ];
        }

        $out = self::resolveIdenticalApiTranslationOutput($title, $out, $sourceLang);

        TranslationCache::updateOrCreate(
            [
                'thread_id' => $threadId,
                'target_lang' => $targetLang,
            ],
            [
                'response_id' => null,
                'source_lang' => $sourceLang,
                'translated_text' => $out,
                'translated_at' => now(),
            ]
        );

        $hasTranslation = self::normalizeTextForIdenticalCompare($out) !== self::normalizeTextForIdenticalCompare($title);

        return ['success' => true, 'display_text' => $out, 'has_translation' => $hasTranslation, 'error' => null, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
    }

    /**
     * 翻訳デバッグ用のセッションフラッシュを載せるか。
     * fetch/AJAX ではレイアウトが描画されず、flash が次の無関係なフルページで遅延アラートになるため記録しない。
     */
    private static function shouldStoreTranslationDebugAlertInSession(): bool
    {
        $req = request();
        if (! $req->hasSession()) {
            return false;
        }
        if ($req->ajax() || $req->wantsJson() || $req->expectsJson()) {
            return false;
        }

        return true;
    }

    /**
     * OpenAI Chat Completions API を呼び出し、先頭のメッセージ本文を返す
     */
    private static function callChatCompletion(string $userPrompt): ?string
    {
        $r = self::callChatCompletionResult($userPrompt);

        return $r['content'];
    }

    /** リプライ・親文脈が長すぎて送信 JSON が上限を超える場合の利用者向け文言キー */
    public const TRANSLATION_USER_MESSAGE_BODY_TOO_LONG = 'translation_ui_body_too_long';

    /** 同一リプライへの翻訳試行上限に達したときの利用者向け文言キー */
    public const TRANSLATION_USER_MESSAGE_RESPONSE_ATTEMPTS_EXHAUSTED = 'translation_ui_response_attempts_exhausted';

    /** 同一スレッドのルーム名への翻訳試行上限に達したときの利用者向け文言キー */
    public const TRANSLATION_USER_MESSAGE_THREAD_TITLE_ATTEMPTS_EXHAUSTED = 'translation_ui_thread_title_attempts_exhausted';

    /**
     * OpenAI 呼び出し結果と、チャット画面用の利用者向けメッセージ区分を返す。
     *
     * @return array{content: ?string, translation_ui_tier: ?string, translation_user_message_key: ?string}
     */
    private static function callChatCompletionResult(string $userPrompt): array
    {
        $apiKey = self::getApiKey();
        if ($apiKey === '') {
            Log::warning('TranslationService: OpenAI API key not configured');

            return [
                'content' => null,
                'translation_ui_tier' => self::TRANSLATION_UI_TIER_ADMIN_REQUIRED,
                'translation_user_message_key' => null,
            ];
        }

        $payload = [
            'model' => self::MODEL,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.3,
            'max_tokens' => 2048,
        ];

        $payloadJson = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        if ($payloadJson === false) {
            Log::warning('TranslationService: OpenAI payload json_encode failed');

            return [
                'content' => null,
                'translation_ui_tier' => self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => self::TRANSLATION_USER_MESSAGE_BODY_TOO_LONG,
            ];
        }
        if (strlen($payloadJson) > self::OPENAI_CHAT_COMPLETION_MAX_JSON_BYTES) {
            Log::warning('TranslationService: OpenAI payload exceeds size limit', ['bytes' => strlen($payloadJson)]);

            return [
                'content' => null,
                'translation_ui_tier' => self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => self::TRANSLATION_USER_MESSAGE_BODY_TOO_LONG,
            ];
        }

        if (self::shouldStoreTranslationDebugAlertInSession()) {
            if (config('app.external_api_debug_alert')) {
                ExternalApiAlertService::record('翻訳API (OpenAI)');
            } elseif (config('services.openai.translation_debug_alert')) {
                session()->flash('translation_api_called', true);
            }
        }

        $outcome = SecureHttpClientService::postWithFailureCode(self::API_URL, $payload, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => true,
        ]);
        $response = $outcome['response'];
        $failureCode = $outcome['failure_code'];

        if ($response === null) {
            Log::warning('TranslationService: OpenAI API request failed (no response)', ['failure_code' => $failureCode]);

            return [
                'content' => null,
                'translation_ui_tier' => self::mapSecureHttpFailureCodeToUiTier($failureCode),
                'translation_user_message_key' => null,
            ];
        }

        if (! $response->successful()) {
            $status = $response->status();
            Log::warning('TranslationService: OpenAI API request failed', [
                'status' => $status,
                'body' => $response->body(),
            ]);
            if ($status === 429 || $status === 408 || $status >= 500) {
                return [
                    'content' => null,
                    'translation_ui_tier' => self::TRANSLATION_UI_TIER_RETRY_LATER,
                    'translation_user_message_key' => null,
                ];
            }

            return [
                'content' => null,
                'translation_ui_tier' => self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => null,
            ];
        }

        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;
        if ($content === null || $content === '') {
            Log::warning('TranslationService: Empty or missing content in OpenAI response', ['json' => $json]);

            return [
                'content' => null,
                'translation_ui_tier' => self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => null,
            ];
        }

        $content = self::normalizeTranslatedContent(trim($content));
        if ($content === '') {
            return [
                'content' => null,
                'translation_ui_tier' => self::TRANSLATION_UI_TIER_NO_RETRY,
                'translation_user_message_key' => null,
            ];
        }

        return ['content' => $content, 'translation_ui_tier' => null, 'translation_user_message_key' => null];
    }

    /**
     * SecureHttpClientService::postWithFailureCode の failure_code をチャット UI 区分に写像する。
     */
    private static function mapSecureHttpFailureCodeToUiTier(?string $failureCode): string
    {
        return match ($failureCode) {
            SecureHttpClientService::FAILURE_INVALID_URL,
            SecureHttpClientService::FAILURE_DOMAIN_NOT_ALLOWED,
            SecureHttpClientService::FAILURE_PRIVATE_IP,
            SecureHttpClientService::FAILURE_METHOD_UNSUPPORTED => self::TRANSLATION_UI_TIER_ADMIN_REQUIRED,
            SecureHttpClientService::FAILURE_DNS_UNRESOLVED,
            SecureHttpClientService::FAILURE_RESPONSE_TOO_LARGE,
            SecureHttpClientService::FAILURE_TRANSPORT,
            SecureHttpClientService::FAILURE_TRANSPORT_TIMEOUT,
            SecureHttpClientService::FAILURE_TRANSPORT_DNS,
            SecureHttpClientService::FAILURE_TRANSPORT_TLS,
            SecureHttpClientService::FAILURE_TRANSPORT_UNKNOWN => self::TRANSLATION_UI_TIER_RETRY_LATER,
            default => self::TRANSLATION_UI_TIER_RETRY_LATER,
        };
    }

    /**
     * API返答の正規化：先頭・末尾の """ とその直後の改行/空白を除去し、改行は保持する
     */
    private static function normalizeTranslatedContent(string $content): string
    {
        // 先頭の """ とその直後の改行・空白を削除
        $content = preg_replace('/^\s*"""\s*/', '', $content);
        // 末尾の """ とその直前の改行・空白を削除
        $content = preg_replace('/\s*"""\s*$/', '', $content);
        $content = trim($content);
        // APIがエスケープした改行 \\n を実改行に変換
        $content = str_replace('\\n', "\n", $content);
        return $content;
    }
}
