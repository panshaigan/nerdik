<?php

declare(strict_types=1);

namespace Tests\Unit\Support;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GdWebpSupportTest extends TestCase
{
    #[Test]
    public function gd_supports_webp_encoding(): void
    {
        $gdInfo = gd_info();

        $this->assertTrue(
            (bool) ($gdInfo['WebP Support'] ?? false),
            'PHP GD must support WebP for profile, event, and activity image uploads.',
        );
    }
}
