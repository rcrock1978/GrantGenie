<?php

declare(strict_types=1);

namespace App\Infrastructure\Storage;

use Illuminate\Support\ServiceProvider;

final class StorageServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(DocumentStorage::class, fn () => new DocumentStorage());
    }
}
