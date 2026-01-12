<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ConsecutiveLoginReward extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'reward_date',
        'coins_rewarded',
        'consecutive_days',
    ];

    protected $casts = [
        'reward_date' => 'date',
    ];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}

