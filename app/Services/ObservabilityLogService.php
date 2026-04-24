<?php

namespace App\Services;

use App\Http\Middleware\RequestCorrelationId;
use App\Models\AccessLog;
use App\Models\ErrorLog;
use App\Models\EventLog;
use App\Models\WalRecoveryLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ObservabilityLogService
{
    public static function requestId(?Request $request = null): string
    {
        $request ??= request();
        return (string) ($request?->attributes->get(RequestCorrelationId::REQUEST_ID_KEY) ?: Str::uuid());
    }

    public static function eventId(?Request $request = null): string
    {
        $request ??= request();
        return (string) ($request?->attributes->get(RequestCorrelationId::EVENT_ID_KEY) ?: Str::uuid());
    }

    public static function recordAccess(array $payload): void
    {
        AccessLog::create($payload);
    }

    public static function recordEvent(array $payload): void
    {
        EventLog::create($payload);
        Log::channel('infra_log')->info('infra_event', $payload);
    }

    public static function recordError(array $payload): void
    {
        ErrorLog::create($payload);
        Log::channel('server_error_log')->error('server_error', $payload);
    }

    public static function recordWalSnapshot(array $payload): void
    {
        WalRecoveryLog::create($payload);
        Log::channel('infra_log')->info('wal_snapshot', $payload);
    }
}
