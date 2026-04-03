<?php

namespace Tests\Unit;

use App\Http\Controllers\ActivityController;
use App\Support\ActivityTypes;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityTypesTest extends TestCase
{
    #[Test]
    public function values_contains_expected_canonical_types(): void
    {
        $values = ActivityTypes::VALUES;

        $this->assertGreaterThan(0, count($values));
        $this->assertSame(count($values), count(array_unique($values)));
        $this->assertContains('rpg', $values);
        $this->assertContains('workshop', $values);
    }

    #[Test]
    public function activity_controller_constant_matches_support_class(): void
    {
        $this->assertSame(ActivityTypes::VALUES, ActivityController::ACTIVITY_TYPES);
    }
}
