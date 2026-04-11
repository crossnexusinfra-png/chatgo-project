<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 翻訳キャッシュ（ルーム名・リプライ本文）
 * - ルーム名: 投稿時に保存した行は期限なし（再翻訳しない）
 * - リプライ: 精度向上のため translated_at から1年で無効化し、表示時に再翻訳し得る
 */
class TranslationCache extends Model
{
    /** リプライ翻訳の有効年数（経過後は isValid=false で再翻訳対象） */
    public const REPLY_TRANSLATION_TTL_YEARS = 1;

    protected $table = 'translation_caches';

    protected $fillable = [
        'thread_id',
        'response_id',
        'source_lang',
        'target_lang',
        'translated_text',
        'translated_at',
    ];

    protected $casts = [
        'translated_at' => 'datetime',
    ];

    public function thread(): BelongsTo
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'thread_id');
    }

    public function response(): BelongsTo
    {
        return $this->belongsTo(Response::class, 'response_id', 'response_id');
    }

    /**
     * キャッシュ行として利用可能か
     */
    public function isValid(): bool
    {
        if (trim((string) $this->translated_text) === '') {
            return false;
        }

        if ($this->response_id !== null) {
            if ($this->translated_at === null) {
                return false;
            }
            return $this->translated_at
                ->copy()
                ->addYears(self::REPLY_TRANSLATION_TTL_YEARS)
                ->isFuture();
        }

        // ルーム名（response_id が null の行）: 期限なし
        return true;
    }
}
