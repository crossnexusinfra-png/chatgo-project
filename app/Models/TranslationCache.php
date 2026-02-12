<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * 翻訳キャッシュ（ルーム名・リプライ本文）
 * 1年経過した翻訳は表示時に再翻訳される
 */
class TranslationCache extends Model
{
    const EXPIRY_YEARS = 1;

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
     * 有効期限内か（translated_at から1年以内か）
     */
    public function isValid(): bool
    {
        return $this->translated_at->addYears(self::EXPIRY_YEARS)->isFuture();
    }
}
