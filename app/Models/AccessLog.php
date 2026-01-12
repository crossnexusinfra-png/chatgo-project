<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AccessLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'type',
        'user_id',
        'path',
        'ip',
    ];
}


