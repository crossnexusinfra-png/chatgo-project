<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class WalRecoveryLog extends Model
{
    use HasFactory;

    protected $fillable = [
        'event_id',
        'request_id',
        'database_driver',
        'database_name',
        'wal_lsn',
        'transaction_id',
        'snapshot_reason',
        'metadata',
    ];

    protected $casts = [
        'metadata' => 'array',
    ];
}
