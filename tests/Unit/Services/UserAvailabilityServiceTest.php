<?php

declare(strict_types=1);

namespace Tests\Unit\Services;

use App\Enums\AvailabilityStatus;
use App\Models\User;
use App\Models\UserAvailabilityDateException;
use App\Models\UserAvailabilityWeekday;
use App\Services\UserAvailabilityService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

final class UserAvailabilityServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_status_is_uncertain_without_defaults(): void
    {
        $user = User::factory()->create();
        $service = app(UserAvailabilityService::class);

        $this->assertSame(
            AvailabilityStatus::Uncertain,
            $service->statusFor($user, Carbon::parse('2026-03-02')),
        );
    }

    public function test_weekday_default_marks_day_available(): void
    {
        $user = User::factory()->create();
        UserAvailabilityWeekday::query()->create([
            'user_id' => $user->id,
            'mon' => true,
        ]);

        $service = app(UserAvailabilityService::class);
        $monday = Carbon::parse('2026-03-02');

        $this->assertSame(1, (int) $monday->isoWeekday());
        $this->assertSame(AvailabilityStatus::Available, $service->statusFor($user, $monday));
        $this->assertSame(AvailabilityStatus::Unavailable, $service->statusFor($user, $monday->copy()->addDay()));
    }

    public function test_date_exception_overrides_weekday_default(): void
    {
        $user = User::factory()->create();
        UserAvailabilityWeekday::query()->create([
            'user_id' => $user->id,
            'mon' => true,
            'tue' => false,
        ]);

        $tuesday = Carbon::parse('2026-03-03');
        UserAvailabilityDateException::query()->create([
            'user_id' => $user->id,
            'date' => $tuesday->toDateString(),
            'status' => AvailabilityStatus::Available,
        ]);

        $service = app(UserAvailabilityService::class);

        $this->assertSame(AvailabilityStatus::Available, $service->statusFor($user, $tuesday));

        $monday = Carbon::parse('2026-03-02');
        UserAvailabilityDateException::query()->create([
            'user_id' => $user->id,
            'date' => $monday->toDateString(),
            'status' => AvailabilityStatus::Unavailable,
        ]);

        $this->assertSame(AvailabilityStatus::Unavailable, $service->statusFor($user, $monday));
    }

    public function test_batch_statuses_for_date_range(): void
    {
        $user = User::factory()->create();
        UserAvailabilityWeekday::query()->create([
            'user_id' => $user->id,
            'sat' => true,
            'sun' => true,
        ]);

        $service = app(UserAvailabilityService::class);
        $start = Carbon::parse('2026-03-07');
        $end = Carbon::parse('2026-03-08');

        $result = $service->statusesForUsersBetween([(int) $user->id], $start, $end);

        $this->assertArrayHasKey('2026-03-07', $result);
        $this->assertSame(
            AvailabilityStatus::Available,
            $result['2026-03-07'][(int) $user->id],
        );
        $this->assertSame(
            AvailabilityStatus::Available,
            $result['2026-03-08'][(int) $user->id],
        );
    }
}
