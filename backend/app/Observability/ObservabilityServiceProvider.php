<?php

declare(strict_types=1);

namespace App\Observability;

use Illuminate\Support\ServiceProvider;

final class ObservabilityServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(Tracer::class, fn () => new Tracer(
            serviceName: env('OTEL_SERVICE_NAME', 'grantgenie-backend'),
        ));
    }
}
