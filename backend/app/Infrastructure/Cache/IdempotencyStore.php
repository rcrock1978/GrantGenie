<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Shared\Clock;
use Illuminate\Support\Facades\Redis;
use Ramsey\Uuid\Uuid;

/**
 * T019 backing store + T031 cache primitive.
 *
 * Stores idempotency keys in Redis with a 24 h TTL. The store is keyed by
 * `(account_id, key)` so a client reusing a key across tenants returns a
 * different lookup. The full envelope is JSON-encoded.
 */
final class IdempotencyStore
{
    public function __construct(private readonly Clock $clock) {}

    public function lookup(string $accountId, string $key): ?array
    {
        $payload = Redis::get($this->key($accountId, $key));
        return $payload === null || $payload === false ? null : json_decode($payload, true);
    }

    public function store(
        string $accountId,
        string $key,
        string $endpoint,
        string $requestHash,
        int $status,
        ?array $body,
        \DateTimeInterface $expiresAt,
    ): void {
        $envelope = [
            'request_hash' => $requestHash,
            'endpoint' => $endpoint,
            'response_status' => $status,
            'response_body' => $body,
            'stored_at' => $this->clock->now()->format(\DateTimeInterface::ATOM),
        ];
        $ttl = max(60, $expiresAt->getTimestamp() - $this->clock->now()->getTimestamp());
        Redis::setex($this->key($accountId, $key), $ttl, json_encode($envelope, JSON_THROW_ON_ERROR));
    }

    private function key(string $accountId, string $key): string
    {
        return "idempotency:{$accountId}:{$key}";
    }
}
