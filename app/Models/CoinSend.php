<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class CoinSend extends Model
{
    use HasFactory;

    protected $fillable = [
        'from_user_id',
        'to_user_id',
        'coins',
        'sent_at',
        'next_available_at',
    ];

    protected $casts = [
        'sent_at' => 'datetime',
        'next_available_at' => 'datetime',
    ];

    public function fromUser()
    {
        return $this->belongsTo(User::class, 'from_user_id', 'user_id');
    }

    public function toUser()
    {
        return $this->belongsTo(User::class, 'to_user_id', 'user_id');
    }
}

