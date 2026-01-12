<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * お知らせメッセージからのコイン受け取り記録モデル
 */
class AdminMessageCoinReward extends Model
{
    use HasFactory;

    /**
     * テーブル名を明示的に指定
     */
    protected $table = 'admin_message_coin_rewards';

    /**
     * 一括代入可能な属性
     */
    protected $fillable = [
        'user_id',
        'admin_message_id',
        'coin_amount',
        'received_at',
    ];

    /**
     * 型キャスト
     */
    protected $casts = [
        'received_at' => 'datetime',
    ];

    /**
     * このコイン受け取り記録が属するユーザー
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * このコイン受け取り記録が属するお知らせメッセージ
     */
    public function adminMessage()
    {
        return $this->belongsTo(AdminMessage::class, 'admin_message_id');
    }
}

