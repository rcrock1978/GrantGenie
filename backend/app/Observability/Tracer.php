<?php

declare(strict_types=1);

namespace App\Observability;

use Throwable;

/**
 * T025 (stub): OpenTelemetry tracing bootstrap.
 *
 * The PHP gRPC + protobuf extensions are not installed in the local dev
 * environment; full SDK wiring is deferred to a follow-up commit (see
 * tasks.md T025). This stub provides the interface and a no-op tracer so
 * downstream code can call `$tracer->span('...')` without a hard dependency
 * on the OTel SDK.
 *
 * The OTLP exporter will be activated when ext-grpc + ext-protobuf land.
 */
final class Tracer
{
    /** @var array<int, array{name:string, started_at:float}> */
    private array $stack = [];

    public function __construct(private readonly string $serviceName) {}

    /**
     * @template T
     * @param  callable():T  $work
     * @return T
     */
    public function span(string $name, callable $work): mixed
    {
        $this->stack[] = ['name' => $name, 'started_at' => microtime(true)];
        try {
            return $work();
        } catch (Throwable $e) {
            // Will emit a span error event once SDK is wired.
            throw $e;
        } finally {
            $span = array_pop($this->stack);
            // No-op for now; will record to OTLP collector in follow-up.
            unset($span);
        }
    }

    public function currentSpanName(): ?string
    {
        $top = end($this->stack);
        return $top === false ? null : $top['name'];
    }
}
