<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ThreadInteraction extends Model
{
    use HasFactory;

    protected $fillable = [
        'thread_id',
        'user_id',
        'other_user_id',
        'message_count',
        'total_characters',
        'last_interaction_at',
    ];

    protected $casts = [
        'last_interaction_at' => 'datetime',
    ];

    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'thread_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function otherUser()
    {
        return $this->belongsTo(User::class, 'other_user_id', 'user_id');
    }
}

