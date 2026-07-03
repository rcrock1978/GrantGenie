<?php

declare(strict_types=1);

namespace App\Exceptions;

use App\Http\Middleware\CorrelationIdMiddleware;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Throwable;
use UnexpectedValueException;

/**
 * T021: ProblemDetailsHandler.
 *
 * Implements FR-017: API errors MUST return RFC 7807 application/problem+json.
 * Replaces Laravel's default HTML/JSON exception responses with a consistent
 * problem+json envelope, always including the request's correlation_id.
 *
 * The exception is also rethrown in non-JSON contexts (e.g. browser routes
 * serving the SPA) so debug pages still work in local dev.
 */
final class ProblemDetailsHandler
{
    public function render(Request $request, Throwable $exception): Response
    {
        if (! $request->expectsJson() && ! $request->is('api/*')) {
            return $this->passthrough($request, $exception);
        }

        $correlationId = (string) $request->attributes->get(CorrelationIdMiddleware::ATTRIBUTE, '');

        [$status, $type, $title, $detail] = $this->classify($exception);

        $problem = [
            'type' => "https://grantgenie.example/problems/{$type}",
            'title' => $title,
            'status' => $status,
            'detail' => $detail,
            'instance' => $request->getRequestUri(),
        ];
        if ($correlationId !== '') {
            $problem['correlation_id'] = $correlationId;
        }
        if ($exception instanceof ValidationException) {
            $problem['errors'] = $exception->errors();
        }

        $headers = ['Content-Type' => 'application/problem+json'];
        if ($exception instanceof HttpExceptionInterface) {
            $headers = array_merge($headers, $exception->getHeaders());
        }

        return new JsonResponse($problem, $status, $headers);
    }

    /**
     * @return array{0:int,1:string,2:string,3:string}
     */
    private function classify(Throwable $e): array
    {
        return match (true) {
            $e instanceof ValidationException => [422, 'validation_failed', 'Validation failed.', $e->getMessage()],
            $e instanceof AuthenticationException => [401, 'unauthorized', 'Authentication required.', $e->getMessage()],
            $e instanceof AuthorizationException => [403, 'forbidden', 'Forbidden.', $e->getMessage()],
            $e instanceof ModelNotFoundException => [404, 'not_found', 'Resource not found.', 'The requested resource does not exist.'],
            $e instanceof NotFoundHttpException => [404, 'not_found', 'Resource not found.', $e->getMessage() ?: 'Route or resource not found.'],
            $e instanceof HttpExceptionInterface => [
                $e->getStatusCode(),
                $this->slugForStatus($e->getStatusCode()),
                $this->titleForStatus($e->getStatusCode()),
                $e->getMessage() !== '' ? $e->getMessage() : $this->titleForStatus($e->getStatusCode()),
            ],
            $e instanceof UnexpectedValueException => [422, 'unexpected_value', 'Unexpected value.', $e->getMessage()],
            default => [500, 'internal_error', 'Internal server error.', app()->isLocal() ? $e->getMessage() : 'An unexpected error occurred.'],
        };
    }

    private function slugForStatus(int $status): string
    {
        return match ($status) {
            400 => 'bad_request',
            401 => 'unauthorized',
            403 => 'forbidden',
            404 => 'not_found',
            409 => 'conflict',
            412 => 'precondition_failed',
            413 => 'payload_too_large',
            415 => 'unsupported_media_type',
            422 => 'unprocessable_entity',
            423 => 'locked',
            429 => 'too_many_requests',
            503 => 'service_unavailable',
            default => 'error',
        };
    }

    private function titleForStatus(int $status): string
    {
        return match ($status) {
            400 => 'Bad request.',
            401 => 'Unauthorized.',
            403 => 'Forbidden.',
            404 => 'Not found.',
            409 => 'Conflict.',
            412 => 'Precondition failed.',
            413 => 'Payload too large.',
            415 => 'Unsupported media type.',
            422 => 'Unprocessable entity.',
            423 => 'Locked.',
            429 => 'Too many requests.',
            503 => 'Service unavailable.',
            default => 'Error.',
        };
    }

    private function passthrough(Request $request, Throwable $exception): Response
    {
        $render = app(\Illuminate\Contracts\Debug\ExceptionHandler::class);
        return $render->render($request, $exception);
    }
}
