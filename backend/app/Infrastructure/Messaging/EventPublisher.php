<?php

declare(strict_types=1);

namespace App\Infrastructure\Messaging;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;

/**
 * T030: EventPublisher.
 *
 * Writes domain events to the `outbox_messages` table in the same DB
 * transaction as the state change. The OutboxPublisher command (T028)
 * drains the table to the Redis Stream `grantgenie.events`.
 *
 * Usage:
 *   DB::transaction(function () use ($publisher) {
 *       $proposal->save();
 *       $publisher->emit('proposal.created', $proposal, ['account_id' => $proposal->account_id]);
 *   });
 */
final class EventPublisher
{
    public function __construct(private readonly Request $request) {}

    public function emit(string $eventType, mixed $aggregate, array $payload, int $eventVersion = 1): void
    {
        $accountId = $aggregate->account_id ?? $payload['account_id'] ?? null;
        if (! is_string($accountId) || $accountId === '') {
            throw new \InvalidArgumentException('Cannot emit event without account_id; pass it explicitly.');
        }

        $envelope = [
            'event_id' => Uuid::uuid4()->toString(),
            'event_type' => $eventType,
            'event_version' => $eventVersion,
            'occurred_at' => now()->toIso8601String(),
            'account_id' => $accountId,
            'correlation_id' => $this->request->attributes->get(\App\Http\Middleware\CorrelationIdMiddleware::ATTRIBUTE),
            'payload' => $payload,
        ];

        DB::table('outbox_messages')->insert([
            'id' => Uuid::uuid4()->toString(),
            'account_id' => $accountId,
            'aggregate_type' => class_basename($aggregate),
            'aggregate_id' => (string) ($aggregate->id ?? $payload['aggregate_id'] ?? Uuid::uuid4()),
            'event_type' => $eventType,
            'event_version' => (string) $eventVersion,
            'payload' => json_encode($envelope, JSON_THROW_ON_ERROR),
            'status' => 'pending',
            'attempts' => 0,
            'next_attempt_at' => now(),
            'created_at' => now(),
        ]);
    }

    /**
     * Internal: called by PublishOutboxCommand to push the envelope to Redis.
     * Kept here (not in the command) so the dependency on Redis is encapsulated.
     */
    public function publishToStream(array $row): void
    {
        $stream = config('messaging.outbox_stream', 'grantgenie.events');
        $payload = json_decode($row['payload'], true, 512, JSON_THROW_ON_ERROR);

        \Illuminate\Support\Facades\Redis::xadd(
            $stream,
            '*',
            [
                'event_id' => $payload['event_id'],
                'envelope' => $row['payload'],
            ],
        );
    }
}
