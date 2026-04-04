<?php

namespace Tests\Unit;

use App\Enums\ActivityType;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityTypeTest extends TestCase
{
    #[Test]
    public function values_contains_expected_canonical_types(): void
    {
        $values = ActivityType::values();

        $this->assertGreaterThan(0, count($values));
        $this->assertSame(count($values), count(array_unique($values)));
        $this->assertContains('rpg', $values);
        $this->assertContains('workshop', $values);
    }
}
