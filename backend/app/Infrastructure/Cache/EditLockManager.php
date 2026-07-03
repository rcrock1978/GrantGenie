<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Shared\Clock;
use Illuminate\Support\Facades\Redis;

/**
 * T031: EditLockManager.
 *
 * Implements the Redis-backed edit lock from research.md R7:
 *   - Acquire: `SET proposal-lock:{proposal_id} {holder_id} NX EX 1800` (30 min TTL).
 *   - Heartbeat: re-`SET` with `XX EX 1800` to refresh.
 *   - Release: `DEL proposal-lock:{proposal_id}` (only if owner).
 *
 * On Redis unavailability, throws — the upstream controller will surface a
 * 503 problem+json response.
 */
final class EditLockManager
{
    public const TTL_SECONDS = 1800;

    public function __construct(private readonly Clock $clock) {}

    public function acquire(string $proposalId, string $userId): ?string
    {
        $key = $this->key($proposalId);
        $value = json_encode([
            'user_id' => $userId,
            'acquired_at' => $this->clock->now()->format(\DateTimeInterface::ATOM),
            'expires_at' => $this->clock->now()->modify('+' . self::TTL_SECONDS . ' seconds')->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);

        $result = Redis::set($key, $value, 'EX', self::TTL_SECONDS, 'NX');
        return $result === true || $result === 'OK' ? $value : null;
    }

    public function heartbeat(string $proposalId, string $userId): bool
    {
        $current = $this->get($proposalId);
        if ($current === null || $current['user_id'] !== $userId) {
            return false;
        }
        $value = json_encode([
            'user_id' => $userId,
            'acquired_at' => $current['acquired_at'],
            'expires_at' => $this->clock->now()->modify('+' . self::TTL_SECONDS . ' seconds')->format(\DateTimeInterface::ATOM),
        ], JSON_THROW_ON_ERROR);
        return Redis::set($this->key($proposalId), $value, 'EX', self::TTL_SECONDS, 'XX') === true;
    }

    public function release(string $proposalId, string $userId): bool
    {
        $current = $this->get($proposalId);
        if ($current === null || $current['user_id'] !== $userId) {
            return false;
        }
        return (int) Redis::del($this->key($proposalId)) > 0;
    }

    public function get(string $proposalId): ?array
    {
        $value = Redis::get($this->key($proposalId));
        return $value === null || $value === false ? null : json_decode($value, true);
    }

    private function key(string $proposalId): string
    {
        return "proposal-lock:{$proposalId}";
    }
}
