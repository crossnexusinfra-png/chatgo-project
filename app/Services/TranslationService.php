<?php

namespace App\Services;

use App\Models\TranslationCache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;

/**
 * GPT-4o mini を用いたルーム名・リプライ本文の翻訳サービス
 *
 * - ルーム名: 投稿時に保存した translation_caches の行は無期限で利用（1年経過でも再翻訳しない）。
 * - リプライ: 保存から TranslationCache::REPLY_TRANSLATION_TTL_YEARS 年でキャッシュ無効となり、表示時に再翻訳し得る。
 * - ルーム名の表示: 一覧・スレッド詳細・通知等はキャッシュのみ（$allowLiveTranslation=false）。APIは主に作成時の他言語キャッシュと、リプライのライブ翻訳経路。
 * - 元言語（source_lang）は「表示言語と比較して翻訳が必要か」の判別にのみ利用する。APIには送らない。
 * - 元言語は送信時の表示言語（threads.source_lang / responses.source_lang）を優先。送信者削除後も正しく判定可能。
 * - 返信の translateReply では、返信元・返信本文ともに「返信先（子）リプライの source_lang」のテキストを渡す（親が別言語なら getParentBodyForReplyTranslationContext で揃える）。
 */
class TranslationService
{
    private const API_URL = 'https://api.openai.com/v1/chat/completions';
    private const MODEL = 'gpt-4o-mini';

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
     * 返信元無し：API翻訳依頼文（{target_language} / {original_text} を置換して送信）
     */
    public static function translateStandalone(string $originalText, string $targetLang): string
    {
        $originalText = trim($originalText);
        if ($originalText === '') {
            return '';
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

        $result = self::callChatCompletion($prompt);
        return $result !== null ? $result : $originalText;
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

        $result = self::callChatCompletion($prompt);
        return $result !== null ? $result : $replyText;
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

        $translated = self::translateStandalone($title, $targetLang);
        if ($translated === $title) {
            return $title;
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

        return $translated;
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

        if ($translated === $body) {
            return $body;
        }

        TranslationCache::updateOrCreate(
            [
                'response_id' => $responseId,
                'target_lang' => $targetLang,
            ],
            [
                'thread_id' => null,
                'source_lang' => $sourceLang,
                'translated_text' => $translated,
                'translated_at' => now(),
            ]
        );

        return $translated;
    }

    /**
     * OpenAI Chat Completions API を呼び出し、先頭のメッセージ本文を返す
     */
    private static function callChatCompletion(string $userPrompt): ?string
    {
        $apiKey = self::getApiKey();
        if ($apiKey === '') {
            Log::warning('TranslationService: OpenAI API key not configured');
            return null;
        }

        // レート制限: 10req/min per user_id（TrustCloudflareProxies + TrustProxies により request()->ip() でクライアントIP取得）
        $clientIp = request()->ip();
        $uid = auth()->id();
        $rateLimitKey = 'openai:' . ($uid ? "user:{$uid}" : "ip:{$clientIp}");
        if (RateLimiter::tooManyAttempts($rateLimitKey, 10)) {
            Log::warning('TranslationService: OpenAI rate limit exceeded (10/min)', ['key' => $rateLimitKey]);
            return null;
        }
        RateLimiter::hit($rateLimitKey, 60);

        if (config('app.external_api_debug_alert')) {
            ExternalApiAlertService::record('翻訳API (OpenAI)');
        } elseif (config('services.openai.translation_debug_alert')) {
            session()->flash('translation_api_called', true);
        }

        $payload = [
            'model' => self::MODEL,
            'messages' => [
                ['role' => 'user', 'content' => $userPrompt],
            ],
            'temperature' => 0.3,
            'max_tokens' => 2048,
        ];

        $response = SecureHttpClientService::post(self::API_URL, $payload, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiKey,
                'Content-Type' => 'application/json',
            ],
            'json' => true,
        ]);

        if ($response === null || !$response->successful()) {
            Log::warning('TranslationService: OpenAI API request failed', [
                'status' => $response ? $response->status() : null,
                'body' => $response ? $response->body() : null,
            ]);
            return null;
        }

        $json = $response->json();
        $content = $json['choices'][0]['message']['content'] ?? null;
        if ($content === null || $content === '') {
            Log::warning('TranslationService: Empty or missing content in OpenAI response', ['json' => $json]);
            return null;
        }

        $content = self::normalizeTranslatedContent(trim($content));
        return $content !== '' ? $content : null;
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
