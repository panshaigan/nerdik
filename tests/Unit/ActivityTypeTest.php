<?php

namespace Tests\Unit;

use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityTypeTest extends TestCase
{
    #[Test]
    public function canonical_activity_type_translation_keys_exist(): void
    {
        $values = array_keys((array) __('ui.activities.types'));

        $this->assertGreaterThan(0, count($values));
        $this->assertSame(count($values), count(array_unique($values)));
        $this->assertContains('rpg', $values);
        $this->assertContains('workshop', $values);
    }
}
