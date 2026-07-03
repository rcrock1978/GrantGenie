<?php

declare(strict_types=1);

namespace App\Domain\Shared;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Domain clock — abstraction over `now()` so tests can inject a frozen clock
 * (T169 SLO dashboards also use this for time-machine helpers per quickstart).
 */
final class Clock
{
    public function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('now', new DateTimeZone('UTC'));
    }

    public function isoNow(): string
    {
        return $this->now()->format(DateTimeInterface::ATOM);
    }
}
