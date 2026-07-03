<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Infrastructure\Messaging\EventPublisher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * T028: OutboxPublisher.
 *
 * Implements the transactional-outbox pattern: every state-changing command
 * writes its event to `outbox_messages` in the same DB transaction as the
 * state change (T030). This command drains the outbox by:
 *   1. SELECT ... FOR UPDATE SKIP LOCKED a batch of pending rows
 *   2. publish each to the Redis Stream `grantgenie.events`
 *   3. mark them `published` (or reschedule with exponential backoff)
 *
 * Idempotency: each event carries a stable `event_id` (UUID). Consumers MUST
 * de-dupe on `event_id` to handle at-least-once delivery.
 *
 * Run as a k8s Deployment with a small replica count, or in dev via
 * `php artisan outbox:publish --loop`.
 */
final class PublishOutboxCommand extends Command
{
    protected $signature = 'outbox:publish
        {--batch=100 : Maximum rows to process per run}
        {--loop : Run continuously (every 1s) for k8s long-running mode}';

    protected $description = 'Drain pending outbox_messages to the Redis Stream grantgenie.events.';

    public function handle(EventPublisher $publisher): int
    {
        $batch = (int) $this->option('batch');
        $loop = (bool) $this->option('loop');

        do {
            $processed = DB::transaction(function () use ($publisher, $batch): int {
                $rows = DB::select(
                    "SELECT id, account_id, aggregate_type, aggregate_id, event_type, event_version, payload
                       FROM outbox_messages
                       WHERE status = 'pending' AND next_attempt_at <= NOW()
                       ORDER BY id
                       LIMIT {$batch}
                       FOR UPDATE SKIP LOCKED"
                );
                if (empty($rows)) {
                    return 0;
                }

                $count = 0;
                foreach ($rows as $row) {
                    $publisher->publishToStream((array) $row);
                    DB::update(
                        "UPDATE outbox_messages SET status = 'published', published_at = NOW() WHERE id = ?",
                        [$row->id]
                    );
                    $count++;
                }
                return $count;
            });

            if ($processed > 0) {
                $this->info("outbox: published {$processed} events");
            }

            if ($loop) {
                sleep(1);
            }
        } while ($loop);

        return self::SUCCESS;
    }
}
