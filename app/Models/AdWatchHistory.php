<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class AdWatchHistory extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'watch_date',
        'watch_count',
    ];

    protected $casts = [
        'watch_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

