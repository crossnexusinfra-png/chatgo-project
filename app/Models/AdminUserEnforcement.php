<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminUserEnforcement extends Model
{
    public const TYPE_RESTRICTION = 'restriction';
    public const TYPE_TEMPORARY_FREEZE = 'temporary_freeze';
    public const TYPE_PERMANENT_FREEZE = 'permanent_freeze';

    protected $fillable = [
        'user_id',
        'enforcement_type',
        'reason',
        'started_at',
        'expires_at',
        'released_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id', 'user_id');
    }

    public function scopeBlocking(Builder $query): Builder
    {
        return $query->whereIn('enforcement_type', [
            self::TYPE_TEMPORARY_FREEZE,
            self::TYPE_PERMANENT_FREEZE,
        ]);
    }

    public function scopeActiveBlocking(Builder $query): Builder
    {
        return $query->blocking()
            ->whereNull('released_at')
            ->where(function (Builder $q) {
                $q->where('enforcement_type', self::TYPE_PERMANENT_FREEZE)
                    ->orWhere(function (Builder $qq) {
                        $qq->where('enforcement_type', self::TYPE_TEMPORARY_FREEZE)
                            ->whereNotNull('expires_at')
                            ->where('expires_at', '>', now());
                    });
            });
    }

    public function scopeActiveRestriction(Builder $query): Builder
    {
        return $query
            ->where('enforcement_type', self::TYPE_RESTRICTION)
            ->whereNull('released_at')
            ->whereNotNull('expires_at')
            ->where('expires_at', '>', now());
    }
}

