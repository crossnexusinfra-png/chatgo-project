<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class FreezeAppeal extends Model
{
    protected $table = 'freeze_appeals';

    protected $primaryKey = 'freeze_appeal_id';

    public $incrementing = true;

    protected $keyType = 'int';

    protected $fillable = [
        'user_id',
        'message',
        'out_count_snapshot',
        'frozen_until_snapshot',
        'is_permanent_snapshot',
        'freeze_period_started_at',
        'status',
        'out_count_reduced',
        'processed_at',
    ];

    protected function casts(): array
    {
        return [
            'out_count_snapshot' => 'float',
            'frozen_until_snapshot' => 'datetime',
            'is_permanent_snapshot' => 'boolean',
            'freeze_period_started_at' => 'datetime',
            'out_count_reduced' => 'float',
            'processed_at' => 'datetime',
        ];
    }

    public function getRouteKeyName(): string
    {
        return 'freeze_appeal_id';
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function isPending(): bool
    {
        return $this->status === 'pending';
    }
}
