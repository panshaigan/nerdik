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

    public function test_format_in_user_tz_applies_twelve_hour_to_display_formats_with_seconds(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        $this->actingAs($user);

        $this->assertSame(
            'Jul 1, 2026 04:30:00 PM',
            format_in_user_tz(Carbon::parse('2026-07-01 14:30:00', 'UTC'), 'M j, Y H:i:s'),
        );
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
            'time_display_format' => TimeDisplayFormat::TwentyFourHour,
        ]);

        $this->actingAs($user);

        $this->assertSame(
            '2026-07-01 16:30',
            format_in_user_tz(Carbon::parse('2026-07-01 14:30:00', 'UTC'), datetime_local_input_format()),
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
            'Wed 1 Jul 2026 · 04:00 PM - 06:00 PM',
            format_datetime_range_compact($start, $end),
        );
    }

    public function test_format_date_range_compact_includes_weekday_for_same_day(): void
    {
        $start = Carbon::parse('2026-11-11 10:00:00', 'UTC');
        $end = Carbon::parse('2026-11-11 18:00:00', 'UTC');

        $this->assertSame(
            'Wed, Nov 11, 2026',
            format_date_range_compact($start, $end),
        );
    }

    public function test_format_date_range_compact_includes_weekdays_for_same_month_range(): void
    {
        $start = Carbon::parse('2026-11-11 10:00:00', 'UTC');
        $end = Carbon::parse('2026-11-15 18:00:00', 'UTC');

        $this->assertSame(
            'Wed, Nov 11 - Sun, 15, 2026',
            format_date_range_compact($start, $end),
        );
    }
}
