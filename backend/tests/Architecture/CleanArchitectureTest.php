<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPat\Selector\Selector;
use PHPat\Test\ArchitectureRule;

/**
 * T033: Architecture rules (Constitution Principle II).
 *
 * Enforces the inward-dependency rule:
 *   - Domain layer: no Illuminate, no Infrastructure, no Application deps.
 *   - Application layer: depends only on Domain.
 *   - Infrastructure/Http: depends on Application ports, not Domain concretes.
 *
 * Run via: vendor/bin/phpstan analyse tests/Architecture
 */
final class CleanArchitectureTest
{
    public function testDomainLayerHasNoFrameworkDeps(): ArchitectureRule
    {
        return new ArchitectureRule(
            Selector::inNamespace('App\\Domain'),
            Selector::NO_USE,
            [
                Selector::inNamespace('App\\Infrastructure'),
                Selector::inNamespace('Illuminate'),
            ]
        );
    }

    public function testApplicationLayerDependsOnlyOnDomain(): ArchitectureRule
    {
        return new ArchitectureRule(
            Selector::inNamespace('App\\Application'),
            Selector::NO_USE,
            Selector::inNamespace('App\\Infrastructure')
        );
    }

    public function testInfrastructureDoesNotDependOnItselfOutsideBoundary(): ArchitectureRule
    {
        return new ArchitectureRule(
            Selector::inNamespace('App\\Infrastructure\\Http'),
            Selector::NO_USE,
            Selector::inNamespace('App\\Domain')
        );
    }
}
