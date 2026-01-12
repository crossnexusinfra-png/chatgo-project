<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class UserInvite extends Model
{
    use HasFactory;

    protected $fillable = [
        'inviter_id',
        'invitee_id',
        'invite_code',
        'coins_given',
        'friend_request_auto_created',
        'invited_at',
    ];

    protected $casts = [
        'coins_given' => 'boolean',
        'friend_request_auto_created' => 'boolean',
        'invited_at' => 'datetime',
    ];

    public function inviter()
    {
        return $this->belongsTo(User::class, 'inviter_id', 'user_id');
    }

    public function invitee()
    {
        return $this->belongsTo(User::class, 'invitee_id', 'user_id');
    }
}

