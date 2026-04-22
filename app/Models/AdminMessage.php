<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_key', 'body_key', 'title', 'body', 'title_ja', 'title_en', 'body_ja', 'body_en', 'audience', 'published_at', 'user_id', 'thread_id', 'response_id', 'reported_user_id', 'allows_reply', 'reply_used', 'parent_message_id', 'unlimited_reply', 'coin_amount', 'is_auto_sent',
        'is_manual_sent', 'is_from_template',
        'is_welcome', 'target_is_adult', 'target_nationalities', 'target_registered_after', 'target_registered_before',
    ];

    /**
     * システムが title_key で識別する自動送信お知らせ
     */
    public const AUTO_SENT_TITLE_KEYS = [
        'r18_change_request_title',
        'report_restriction_review_title',
        'report_restriction_ack_title',
        'suggestion_received_title',
    ];

    /**
     * 旧データ互換用: title_key が無い自動送信お知らせの固定タイトル
     */
    public const AUTO_SENT_LEGACY_TITLES = [
        '通報内容の対応について',
        '削除処理完了のお知らせ',
        '改善要望の対応について',
        '利用に関する警告',
        'アカウント一時凍結のお知らせ',
        'アカウント永久凍結のお知らせ',
        'ルーム削除のお知らせ',
        'リプライ削除のお知らせ',
        'プロフィール削除のお知らせ',
        'Update on Your Report',
        'Deletion Completed',
        'Update on Your Suggestion',
        'Warning Notice',
        'Temporary Account Suspension',
        'Permanent Account Suspension',
        'Room Deletion Notice',
        'Reply Deletion Notice',
        'Profile Deletion Notice',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'target_registered_after' => 'datetime',
        'target_registered_before' => 'datetime',
        'target_nationalities' => 'array',
        'allows_reply' => 'boolean',
        'reply_used' => 'boolean',
        'unlimited_reply' => 'boolean',
        'is_welcome' => 'boolean',
        'is_auto_sent' => 'boolean',
        'is_manual_sent' => 'boolean',
        'is_from_template' => 'boolean',
    ];
    
    /**
     * このメッセージの送信先ユーザー（個人向けメッセージの場合・1名）
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * 特定複数人向けの送信先（admin_message_recipients）
     */
    public function recipients()
    {
        return $this->belongsToMany(User::class, 'admin_message_recipients', 'admin_message_id', 'user_id', 'id', 'user_id')
            ->withTimestamps();
    }
    
    /**
     * 親メッセージ（返信の場合）
     */
    public function parentMessage()
    {
        return $this->belongsTo(AdminMessage::class, 'parent_message_id');
    }
    
    /**
     * 返信メッセージ
     */
    public function replies()
    {
        return $this->hasMany(AdminMessage::class, 'parent_message_id');
    }

    /**
     * このメッセージを読んだユーザーのリレーション
     */
    public function reads()
    {
        return $this->hasMany(AdminMessageRead::class, 'admin_message_id');
    }

    /**
     * このメッセージからコインを受け取ったユーザーのリレーション
     */
    public function coinRewards()
    {
        return $this->hasMany(AdminMessageCoinReward::class, 'admin_message_id');
    }

    /**
     * システム自動送信のお知らせを除外（is_auto_sent / title_key ベース）
     */
    public function scopeExcludingSystemAutoNotifications(Builder $query): Builder
    {
        if (\Illuminate\Support\Facades\Schema::hasColumn('admin_messages', 'is_manual_sent')) {
            $keys = self::AUTO_SENT_TITLE_KEYS;
            $legacyAutoTitles = self::AUTO_SENT_LEGACY_TITLES;

            return $query->where(function (Builder $manual) use ($keys, $legacyAutoTitles) {
                // 新仕様: 明示的に手動送信として保存されたもの
                $manual->where('is_manual_sent', true)
                    // 旧データ互換: is_manual_sent が未設定でも、自動送信条件に該当しない既存送信済みは手動扱いにする
                    ->orWhere(function (Builder $legacy) use ($keys, $legacyAutoTitles) {
                        $legacy->whereNull('is_manual_sent')
                            ->where(function (Builder $w) {
                                $w->whereNull('is_auto_sent')->orWhere('is_auto_sent', false);
                            })
                            ->where(function (Builder $w) use ($keys) {
                                $w->whereNull('title_key')->orWhereNotIn('title_key', $keys);
                            })
                            ->where(function (Builder $w) use ($legacyAutoTitles) {
                                $w->whereNull('title')->orWhereNotIn('title', $legacyAutoTitles);
                            });
                    });
            });
        }

        $keys = self::AUTO_SENT_TITLE_KEYS;
        return $query->where(function (Builder $q) use ($keys) {
            $q->where(function (Builder $w) {
                $w->whereNull('is_auto_sent')->orWhere('is_auto_sent', false);
            })->where(function (Builder $w) use ($keys) {
                $w->whereNull('title_key')->orWhereNotIn('title_key', $keys);
            });
        });
    }

    /**
     * 指定されたユーザーがこのメッセージからコインを受け取ったかどうかを判定
     */
    public function hasReceivedCoin(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        return $this->coinRewards()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 指定されたユーザーがこのメッセージを読んだかどうかを判定
     */
    public function isReadBy(?int $userId): bool
    {
        if (!$userId) {
            return false;
        }
        // リレーションを使って開封済みかチェック
        return $this->reads()
            ->where('user_id', $userId)
            ->exists();
    }

    /**
     * 現在の言語に応じたタイトルを取得
     */
    public function getTitleAttribute(): ?string
    {
        // キーが設定されている場合はLanguageServiceから取得
        // $this->attributes を直接使用して無限ループを回避
        $titleKey = $this->attributes['title_key'] ?? null;
        if ($titleKey) {
            return \App\Services\LanguageService::trans($titleKey);
        }
        // 多言語カラムがある場合はユーザー言語を優先
        $lang = strtoupper((string) \App\Services\LanguageService::getCurrentLanguage());
        if ($lang === 'EN' && array_key_exists('title_en', $this->attributes) && !empty($this->attributes['title_en'])) {
            return $this->attributes['title_en'];
        }
        if (array_key_exists('title_ja', $this->attributes) && !empty($this->attributes['title_ja'])) {
            return $this->attributes['title_ja'];
        }
        // 直接保存されている場合はそのまま返す
        return $this->attributes['title'] ?? null;
    }

    /**
     * 現在の言語に応じた本文を取得
     */
    public function getBodyAttribute(): string
    {
        // キーが設定されている場合はLanguageServiceから取得
        // $this->attributes を直接使用して無限ループを回避
        $bodyKey = $this->attributes['body_key'] ?? null;
        if ($bodyKey) {
            $body = \App\Services\LanguageService::trans($bodyKey);
            // プレースホルダーを置換（動的な内容がある場合）
            // 注意: body_paramsは現在実装されていませんが、将来的な拡張のために残しています
            return $body;
        }
        // 多言語カラムがある場合はユーザー言語を優先
        $lang = strtoupper((string) \App\Services\LanguageService::getCurrentLanguage());
        if ($lang === 'EN' && array_key_exists('body_en', $this->attributes) && !empty($this->attributes['body_en'])) {
            return $this->attributes['body_en'];
        }
        if (array_key_exists('body_ja', $this->attributes) && !empty($this->attributes['body_ja'])) {
            return $this->attributes['body_ja'];
        }
        // 直接保存されている場合はそのまま返す
        return $this->attributes['body'] ?? '';
    }
}


