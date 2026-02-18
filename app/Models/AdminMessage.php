<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdminMessage extends Model
{
    use HasFactory;

    protected $fillable = [
        'title_key', 'body_key', 'title', 'body', 'audience', 'published_at', 'user_id', 'thread_id', 'allows_reply', 'reply_used', 'parent_message_id', 'unlimited_reply', 'coin_amount',
        'is_welcome', 'target_is_adult', 'target_nationalities', 'target_registered_after', 'target_registered_before',
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
        // 直接保存されている場合はそのまま返す
        return $this->attributes['body'] ?? '';
    }
}


