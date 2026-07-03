<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domain\Shared\Clock;
use App\Infrastructure\Cache\IdempotencyStore;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * T019: Idempotency-Key middleware.
 *
 * Implements FR-016: all command endpoints (POST/PUT/PATCH/DELETE) MUST support
 * idempotency keys. The client supplies a UUID via the `Idempotency-Key` header;
 * we cache the response in Redis for 24 h and replay it on repeat calls.
 *
 * Per request validation rules:
 *  - Header is required on non-GET/HEAD requests.
 *  - Same key + same body hash → replay cached response.
 *  - Same key + different body → 409 Conflict (likely a client bug).
 *  - First call → execute handler, cache the response.
 */
final class IdempotencyKey
{
    public function __construct(
        private readonly IdempotencyStore $store,
        private readonly Clock $clock,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->method(), ['GET', 'HEAD', 'OPTIONS'], true)) {
            return $next($request);
        }

        $key = $request->header('Idempotency-Key');
        if ($key === null || $key === '') {
            return response()->problem('idempotency_key_required',
                'Idempotency-Key header is required for mutating requests.', 400);
        }

        $accountId = $request->attributes->get('oidc_account_id') ?? 'anonymous';
        $endpoint = $request->method() . ' ' . $request->path();
        $bodyHash = hash('sha256', (string) $request->getContent());

        $cached = $this->store->lookup($accountId, $key);
        if ($cached !== null) {
            if (($cached['request_hash'] ?? null) !== $bodyHash) {
                return response()->problem('idempotency_key_conflict',
                    'Idempotency-Key reused with a different request body.', 409);
            }
            if ($cached['response_status'] !== null && $cached['response_body'] !== null) {
                return response()->json($cached['response_body'], $cached['response_status']);
            }
        }

        /** @var Response $response */
        $response = $next($request);

        $this->store->store(
            accountId: $accountId,
            key: $key,
            endpoint: $endpoint,
            requestHash: $bodyHash,
            status: $response->getStatusCode(),
            body: $this->safeResponseBody($response),
            expiresAt: $this->clock->now()->modify('+24 hours'),
        );

        return $response;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function safeResponseBody(Response $response): ?array
    {
        $content = $response->getContent();
        if ($content === '' || $content === false) {
            return null;
        }
        $decoded = json_decode($content, true);
        return is_array($decoded) ? $decoded : null;
    }
}
