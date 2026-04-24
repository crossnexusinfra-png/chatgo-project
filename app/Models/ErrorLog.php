<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ErrorLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'error_id',
        'request_id',
        'event_id',
        'source',
        'status_code',
        'error_type',
        'message',
        'path',
        'method',
        'ip',
        'user_id',
        'context',
    ];

    protected $casts = [
        'context' => 'array',
    ];
}
