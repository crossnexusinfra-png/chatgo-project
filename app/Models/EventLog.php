<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class EventLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'request_id',
        'event_type',
        'source',
        'user_id',
        'path',
        'ip',
        'payload',
    ];

    protected $casts = [
        'payload' => 'array',
    ];
}
