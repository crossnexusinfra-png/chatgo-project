<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestCorrelationId
{
    public const REQUEST_ID_KEY = 'request_id';
    public const EVENT_ID_KEY = 'event_id';

    public function handle(Request $request, Closure $next): Response
    {
        $requestId = (string) ($request->headers->get('X-Request-Id') ?: Str::uuid());
        $eventId = (string) Str::uuid();

        $request->attributes->set(self::REQUEST_ID_KEY, $requestId);
        $request->attributes->set(self::EVENT_ID_KEY, $eventId);

        /** @var Response $response */
        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
