<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use App\Infrastructure\External\AIServiceClient;
use Illuminate\Support\ServiceProvider;

final class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(EventPublisher::class, fn ($app) => new EventPublisher($app['request']));
        $this->app->singleton(AIServiceClient::class, fn ($app) => new AIServiceClient(
            $app->make(\Illuminate\Http\Client\Factory::class),
            $app->make(\App\Observability\Tracer::class),
        ));
    }
}
