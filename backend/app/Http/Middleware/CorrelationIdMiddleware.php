<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Ramsey\Uuid\Uuid;
use Symfony\Component\HttpFoundation\Response;

/**
 * T020: CorrelationIdMiddleware.
 *
 * Generates or propagates an `X-Correlation-Id` for every request, attaches it
 * to the request as an attribute, and echoes it on the response. The same
 * correlation_id MUST be injected into:
 *  - every structured log line via Monolog processor (T024)
 *  - every outbox event payload (T030)
 *  - every AI service outbound call via `X-Correlation-Id` header (T029)
 *
 * Implements FR-015 and SC-006 (100% of requests carry correlation IDs).
 */
final class CorrelationIdMiddleware
{
    public const ATTRIBUTE = 'correlation_id';
    public const HEADER = 'X-Correlation-Id';

    public function handle(Request $request, Closure $next): Response
    {
        $incoming = $request->header(self::HEADER);
        $correlationId = ($incoming !== null && $this->isValid($incoming))
            ? $incoming
            : Uuid::uuid4()->toString();

        $request->attributes->set(self::ATTRIBUTE, $correlationId);
        Log::shareContext(['correlation_id' => $correlationId]);

        /** @var Response $response */
        $response = $next($request);

        $response->headers->set(self::HEADER, $correlationId);

        return $response;
    }

    private function isValid(string $value): bool
    {
        return strlen($value) <= 64 && preg_match('/^[A-Za-z0-9._\-]+$/', $value) === 1;
    }
}
