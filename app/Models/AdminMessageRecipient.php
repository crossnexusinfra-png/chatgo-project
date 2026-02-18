<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminMessageRecipient extends Model
{
    protected $table = 'admin_message_recipients';

    protected $fillable = ['admin_message_id', 'user_id'];

    public function adminMessage(): BelongsTo
    {
        return $this->belongsTo(AdminMessage::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }
}
