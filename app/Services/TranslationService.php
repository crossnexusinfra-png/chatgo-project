<?php

namespace App\Services;

use App\Models\TranslationCache;
use Illuminate\Support\Facades\Log;

/**
 * GPT-4o mini を用いたルーム名・リプライ本文の翻訳サービス
 *
 * - 翻訳結果はDBに1年間保存。1年経過後は表示時に再翻訳する。
 * - 元言語（source_lang）は「表示言語と比較して翻訳が必要か」の判別にのみ利用する。APIには送らない。
 * - 元言語は送信時の表示言語（threads.source_lang / responses.source_lang）を優先。送信者削除後も正しく判定可能。
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
            . "- Return only the translated text.\n\n"
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
            . "- Return only the translated reply.\n\n"
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
     * ルーム名の表示用テキストを取得（DBキャッシュ優先、1年で期限切れなら再翻訳）
     *
     * @param int $threadId スレッドID
     * @param string $title 元のルーム名（getCleanTitle 済み推奨）
     * @param string $targetLang 表示言語（JA / EN）
     * @param string $sourceLang 送信時の表示言語（threads.source_lang。翻訳要否の判別にのみ使用）
     * @return string 表示用テキスト
     */
    public static function getTranslatedThreadTitle(int $threadId, string $title, string $targetLang, string $sourceLang): string
    {
        $title = trim($title);
        $targetLang = self::normalizeLang($targetLang);
        $sourceLang = self::normalizeLang($sourceLang);
        if ($title === '') {
            return '';
        }

        $cached = TranslationCache::where('thread_id', $threadId)
            ->where('target_lang', $targetLang)
            ->first();

        if ($cached && $cached->isValid()) {
            return $cached->translated_text;
        }

        if (!self::shouldTranslate($sourceLang, $targetLang)) {
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
     * リプライ本文の表示用テキストを取得（DBキャッシュ優先、1年で期限切れなら再翻訳）
     *
     * @param int $responseId レスポンスID
     * @param string $body 元の本文
     * @param string $targetLang 表示言語（JA / EN）
     * @param string|null $parentBody 親リプライ本文（返信の場合）
     * @param string $sourceLang 送信時の表示言語（responses.source_lang。翻訳要否の判別にのみ使用）
     * @return string 表示用テキスト
     */
    public static function getTranslatedResponseBody(int $responseId, string $body, string $targetLang, ?string $parentBody, string $sourceLang): string
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

        // テスト用: 翻訳APIを呼び出す直前にセッションにフラグを立て、表示側でアラート可能にする
        if (config('services.openai.translation_debug_alert')) {
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
     * API返答の正規化：前後の """ を除去し、改行は保持する
     */
    private static function normalizeTranslatedContent(string $content): string
    {
        // 前後の """ で囲まれた部分のみを取り出す（改行は保持）
        if (preg_match('/^"""\s*\n?(.*)\n?\s*"""$/s', $content, $m)) {
            $content = trim($m[1]);
        } elseif (preg_match('/^"""\s*\n?(.*)$/s', $content, $m)) {
            $content = trim(preg_replace('/\s*"""\s*$/s', '', $m[1]));
        }
        // APIがエスケープした改行 \\n を実改行に変換
        $content = str_replace('\\n', "\n", $content);
        return $content;
    }
}
