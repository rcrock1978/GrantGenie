<?php

declare(strict_types=1);

use App\Exceptions\ProblemDetailsHandler;
use App\Http\Middleware\CorrelationIdMiddleware;
use App\Http\Middleware\IdempotencyKey;
use App\Http\Middleware\TenantScope;
use App\Http\Middleware\VerifyOidcToken;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Spatie\Csp\AddCspHeaders;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        apiPrefix: 'api',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Global middleware applied to every request
        $middleware->append(CorrelationIdMiddleware::class);

        // Aliases for use in routes/api.php
        $middleware->alias([
            'oidc' => VerifyOidcToken::class,
            'tenant' => TenantScope::class,
            'idempotent' => IdempotencyKey::class,
        ]);

        // API middleware group ordering
        $middleware->api(prepend: [
            // (none — auth is per-route)
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // FR-017: API errors return RFC 7807 problem+json
        $exceptions->render(fn (\Illuminate\Http\Request $request, \Throwable $e) => app(ProblemDetailsHandler::class)->render($request, $e));
    })->create();
