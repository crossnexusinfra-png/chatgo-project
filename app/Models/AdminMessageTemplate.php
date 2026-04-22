<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AdminMessageTemplate extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'title_ja',
        'title_en',
        'body_ja',
        'body_en',
        'coin_amount',
    ];

    protected $casts = [
        'coin_amount' => 'integer',
    ];
}
