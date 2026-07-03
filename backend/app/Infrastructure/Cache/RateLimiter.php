<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use Illuminate\Support\Facades\Redis;
use RuntimeException;

/**
 * T031: RateLimiter.
 *
 * Per-user / per-tenant fixed-window counter in Redis. Returns true if the
 * caller is within the budget; false if the limit is exceeded.
 *
 * Key: `ratelimit:{bucket}:{identifier}:{window_id}` where `window_id` is the
 * floor(now / window_seconds). Auto-expires after 2 * window_seconds.
 */
final class RateLimiter
{
    public function hit(string $bucket, string $identifier, int $limit, int $windowSeconds): bool
    {
        $windowId = intdiv(time(), $windowSeconds);
        $key = "ratelimit:{$bucket}:{$identifier}:{$windowId}";

        $current = (int) Redis::incr($key);
        if ($current === 1) {
            Redis::expire($key, $windowSeconds * 2);
        }
        return $current <= $limit;
    }
}
