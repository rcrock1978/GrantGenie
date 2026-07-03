<?php

return [
    App\Providers\AppServiceProvider::class,
    App\Observability\ObservabilityServiceProvider::class,
    App\Infrastructure\Cache\CacheServiceProvider::class,
    App\Infrastructure\Storage\StorageServiceProvider::class,
    App\Infrastructure\Messaging\MessagingServiceProvider::class,
    App\Infrastructure\External\AIServiceClient::class,
    App\Domain\Shared\SharedServiceProvider::class,
];
