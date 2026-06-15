<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProfileTimezoneOptionsTest extends TestCase
{
    public function test_profile_timezone_options_use_iana_labels(): void
    {
        $warsaw = collect(profile_timezone_options())
            ->firstWhere('id', 'Europe/Warsaw');

        $this->assertIsArray($warsaw);
        $this->assertSame('Europe/Warsaw', $warsaw['id']);
        $this->assertSame('Europe/Warsaw', $warsaw['name']);
    }

    public function test_profile_timezone_ids_match_options(): void
    {
        $optionIds = array_column(profile_timezone_options(), 'id');

        $this->assertSame(profile_timezone_ids(), $optionIds);
    }
}
