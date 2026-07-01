<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TimeDisplayFormat;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DisplayTimeFormatTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_defaults_to_twenty_four_hour(): void
    {
        $this->assertSame(TimeDisplayFormat::TwentyFourHour, display_time_format());
    }

    public function test_authenticated_user_with_factory_default_uses_twenty_four_hour(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $this->assertSame(TimeDisplayFormat::TwentyFourHour, display_time_format());
        $this->assertSame(TimeDisplayFormat::TwentyFourHour, $user->profile?->time_display_format);
    }

    public function test_authenticated_user_with_twelve_hour_preference(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['time_display_format' => TimeDisplayFormat::TwelveHour]);

        $this->actingAs($user);

        $this->assertSame(TimeDisplayFormat::TwelveHour, display_time_format());
    }

    public function test_authenticated_user_with_twenty_four_hour_preference(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['time_display_format' => TimeDisplayFormat::TwentyFourHour]);

        $this->actingAs($user);

        $this->assertSame(TimeDisplayFormat::TwentyFourHour, display_time_format());
    }
}
