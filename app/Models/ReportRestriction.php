<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ReportRestriction extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'thread_id',
        'response_id',
        'reported_user_id',
        'status',
        'acknowledged_at',
        'admin_message_id',
    ];

    protected function casts(): array
    {
        return [
            'acknowledged_at' => 'datetime',
        ];
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function thread()
    {
        return $this->belongsTo(Thread::class, 'thread_id', 'thread_id');
    }

    public function response()
    {
        return $this->belongsTo(Response::class, 'response_id', 'response_id');
    }
}

