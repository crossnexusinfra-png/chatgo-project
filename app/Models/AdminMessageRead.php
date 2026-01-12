<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

/**
 * お知らせメッセージの開封記録モデル
 * 
 * ユーザーがお知らせメッセージを開封した記録を保存します。
 */
class AdminMessageRead extends Model
{
    use HasFactory;

    /**
     * テーブル名を明示的に指定
     */
    protected $table = 'admin_message_reads';

    /**
     * 一括代入可能な属性
     */
    protected $fillable = [
        'user_id',
        'admin_message_id',
        'read_at',
    ];

    /**
     * 型キャスト
     */
    protected $casts = [
        'read_at' => 'datetime',
    ];

    /**
     * この開封記録が属するユーザー
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * この開封記録が属するお知らせメッセージ
     */
    public function adminMessage()
    {
        return $this->belongsTo(AdminMessage::class, 'admin_message_id');
    }
}

