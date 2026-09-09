<?php

declare(strict_types=1);

namespace Tests\PHPUnit;

use PHPUnit\Runner\Extension\Extension;
use PHPUnit\Runner\Extension\Facade;
use PHPUnit\Runner\Extension\ParameterCollection;
use PHPUnit\TextUI\Configuration\Configuration;

/**
 * Configures truncation length for assertSee / assertStringContainsString failure dumps.
 */
final class TruncateLongStringExportsExtension implements Extension
{
    public function bootstrap(Configuration $configuration, Facade $facade, ParameterCollection $parameters): void
    {
        AssertionHaystackTruncation::configure(
            $parameters->has('maxLength')
                ? (int) $parameters->get('maxLength')
                : 800,
        );
    }
}
