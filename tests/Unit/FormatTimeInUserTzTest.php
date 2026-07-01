<?php

declare(strict_types=1);

namespace Tests\Unit;

use App\Enums\TimeDisplayFormat;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FormatTimeInUserTzTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Carbon::setTestNow(Carbon::parse('2026-07-01 14:30:00', 'UTC'));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_format_time_in_user_tz_uses_twenty_four_hour_by_default(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwentyFourHour,
        ]);

        $this->actingAs($user);

        $this->assertSame('16:30', format_time_in_user_tz(Carbon::parse('2026-07-01 14:30:00', 'UTC')));
    }

    public function test_format_time_in_user_tz_uses_twelve_hour_when_preferred(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        $this->actingAs($user);

        $this->assertSame('04:30 PM', format_time_in_user_tz(Carbon::parse('2026-07-01 14:30:00', 'UTC')));
    }

    public function test_format_in_user_tz_applies_twelve_hour_to_display_formats(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        $this->actingAs($user);

        $this->assertSame(
            '04:30 PM',
            format_in_user_tz(Carbon::parse('2026-07-01 14:30:00', 'UTC'), 'H:i'),
        );
    }

    public function test_format_in_user_tz_keeps_datetime_local_pattern_in_twenty_four_hour(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        $this->actingAs($user);

        $this->assertSame(
            '2026-07-01T16:30',
            format_in_user_tz(Carbon::parse('2026-07-01 14:30:00', 'UTC'), 'Y-m-d\TH:i'),
        );
    }

    public function test_format_datetime_in_user_tz_applies_twelve_hour_to_iso_tokens(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        $this->actingAs($user);

        $this->assertSame(
            '1 Jul 2026, 4:30 PM',
            format_datetime_in_user_tz(Carbon::parse('2026-07-01 14:30:00', 'UTC'), 'D MMM YYYY, HH:mm'),
        );
    }

    public function test_format_datetime_in_user_tz_default_lll_respects_twelve_hour_preference(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        $this->actingAs($user);

        $this->assertSame(
            '1 Jul 2026, 4:30 PM',
            format_datetime_in_user_tz(Carbon::parse('2026-07-01 14:30:00', 'UTC')),
        );
    }

    public function test_format_datetime_range_compact_respects_twelve_hour_preference(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        $this->actingAs($user);

        $start = Carbon::parse('2026-07-01 14:00:00', 'UTC');
        $end = Carbon::parse('2026-07-01 16:00:00', 'UTC');

        $this->assertSame(
            '1 Jul 2026 · 04:00 PM - 06:00 PM',
            format_datetime_range_compact($start, $end),
        );
    }
}
