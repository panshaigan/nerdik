<?php

namespace Tests\Unit;

use Tests\TestCase;

class ProfileTimezoneOptionsTest extends TestCase
{
    public function test_default_profile_timezone_is_europe_warsaw(): void
    {
        $this->assertSame('Europe/Warsaw', default_profile_timezone());
    }

    public function test_profile_timezone_ids_list_europe_warsaw_first(): void
    {
        $this->assertSame('Europe/Warsaw', profile_timezone_ids()[0]);
        $this->assertContains('UTC', profile_timezone_ids());
    }

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
