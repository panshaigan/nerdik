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

    #[Test]
    public function canonical_activity_type_propose_noun_keys_exist(): void
    {
        $typeSlugs = array_keys((array) __('ui.activities.types'));
        $nounSlugs = array_keys((array) __('ui.activities.propose_nouns'));

        $this->assertSame([], array_diff($typeSlugs, $nounSlugs));
        $this->assertSame([], array_diff($nounSlugs, $typeSlugs));
        $this->assertNotSame('', trim((string) __('ui.activities.propose_noun_fallback')));
    }
}
