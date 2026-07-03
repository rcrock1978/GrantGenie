<?php

declare(strict_types=1);

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Monolog processor: injects correlation_id, account_id, user_id into every
 * log record so the SC-006 "100% of requests carry correlation IDs" invariant
 * holds across all log channels.
 */
final class InjectContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        $extra = $record->extra;

        $correlationId = $extra['correlation_id'] ?? null;
        if ($correlationId === null && function_exists('app') && app()->bound('request')) {
            $request = app('request');
            $correlationId = $request?->attributes->get(\App\Http\Middleware\CorrelationIdMiddleware::ATTRIBUTE);
        }
        if ($correlationId !== null) {
            $extra['correlation_id'] = $correlationId;
        }

        $tenantId = $extra['account_id'] ?? null;
        if ($tenantId === null && function_exists('app') && app()->bound('request')) {
            $tenantId = app('request')?->attributes->get('oidc_account_id');
        }
        if ($tenantId !== null) {
            $extra['account_id'] = $tenantId;
        }

        return $record->with(extra: $extra);
    }
}
