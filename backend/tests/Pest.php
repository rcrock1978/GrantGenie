<?php

declare(strict_types=1);

use Illuminate\Foundation\Testing\RefreshDatabase;

pest()
    ->extend(Tests\TestCase::class)
    ->use(RefreshDatabase::class)
    ->in('Feature', 'Unit', 'Integration', 'Contract', 'Architecture', 'Audit', 'Boilerplate', 'Discovery', 'Errors', 'Idempotency', 'Onboarding', 'Proposal', 'Review', 'Tracking', 'Safety');
