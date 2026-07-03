<?php

declare(strict_types=1);

namespace App\Infrastructure\Cache;

use App\Domain\Shared\Clock;
use App\Infrastructure\Cache\EditLockManager;
use App\Infrastructure\Cache\IdempotencyStore;
use App\Infrastructure\Cache\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Redis;

final class CacheServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(IdempotencyStore::class, fn ($app) => new IdempotencyStore($app->make(Clock::class)));
        $this->app->singleton(EditLockManager::class, fn ($app) => new EditLockManager($app->make(Clock::class)));
        $this->app->singleton(RateLimiter::class, fn () => new RateLimiter());
    }
}
