<?php

declare(strict_types=1);

namespace App\Providers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Response;
use Illuminate\Support\ServiceProvider;

final class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // RFC 7807 problem+json shortcut: response()->problem($type, $title, $status, $detail)
        Response::macro('problem', function (string $type, string $title, int $status = 400, ?string $detail = null): JsonResponse {
            $body = [
                'type' => "https://grantgenie.example/problems/{$type}",
                'title' => $title,
                'status' => $status,
            ];
            if ($detail !== null) {
                $body['detail'] = $detail;
            }
            /** @var Request $request */
            $request = app('request');
            $correlationId = $request?->attributes->get(\App\Http\Middleware\CorrelationIdMiddleware::ATTRIBUTE);
            if ($correlationId !== null) {
                $body['correlation_id'] = $correlationId;
            }
            return new JsonResponse($body, $status, ['Content-Type' => 'application/problem+json']);
        });
    }
}
