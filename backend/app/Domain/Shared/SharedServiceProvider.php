<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use Illuminate\Support\ServiceProvider;

final class SharedServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Clock::class, fn () => new Clock());
    }
}
