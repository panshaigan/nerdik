<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\AvailabilityStatus;
use App\Models\User;
use App\Models\UserAvailabilityDateException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PruneAvailabilityExceptionsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_past_exceptions_and_keeps_today_and_future(): void
    {
        Config::set('housekeeping.availability_exceptions_grace_days', 0);

        $user = User::factory()->create();
        $past = UserAvailabilityDateException::query()->create([
            'user_id' => $user->id,
            'date' => now()->subDay()->toDateString(),
            'status' => AvailabilityStatus::Unavailable,
        ]);
        $today = UserAvailabilityDateException::query()->create([
            'user_id' => $user->id,
            'date' => now()->toDateString(),
            'status' => AvailabilityStatus::Available,
        ]);
        $future = UserAvailabilityDateException::query()->create([
            'user_id' => $user->id,
            'date' => now()->addDay()->toDateString(),
            'status' => AvailabilityStatus::Uncertain,
        ]);

        $this->artisan('housekeeping:prune-availability-exceptions')
            ->assertSuccessful()
            ->expectsOutputToContain('pruned=1');

        $this->assertDatabaseMissing('user_availability_date_exceptions', ['id' => $past->id]);
        $this->assertDatabaseHas('user_availability_date_exceptions', ['id' => $today->id]);
        $this->assertDatabaseHas('user_availability_date_exceptions', ['id' => $future->id]);
    }

    #[Test]
    public function grace_days_preserve_recent_past_exceptions(): void
    {
        Config::set('housekeeping.availability_exceptions_grace_days', 7);

        $user = User::factory()->create();
        $withinGrace = UserAvailabilityDateException::query()->create([
            'user_id' => $user->id,
            'date' => now()->subDays(3)->toDateString(),
            'status' => AvailabilityStatus::Unavailable,
        ]);
        $beyondGrace = UserAvailabilityDateException::query()->create([
            'user_id' => $user->id,
            'date' => now()->subDays(10)->toDateString(),
            'status' => AvailabilityStatus::Unavailable,
        ]);

        $this->artisan('housekeeping:prune-availability-exceptions')
            ->assertSuccessful()
            ->expectsOutputToContain('pruned=1');

        $this->assertDatabaseHas('user_availability_date_exceptions', ['id' => $withinGrace->id]);
        $this->assertDatabaseMissing('user_availability_date_exceptions', ['id' => $beyondGrace->id]);
    }

    #[Test]
    public function dry_run_does_not_delete_past_exceptions(): void
    {
        Config::set('housekeeping.availability_exceptions_grace_days', 0);

        $user = User::factory()->create();
        $past = UserAvailabilityDateException::query()->create([
            'user_id' => $user->id,
            'date' => now()->subDays(5)->toDateString(),
            'status' => AvailabilityStatus::Unavailable,
        ]);

        $this->artisan('housekeeping:prune-availability-exceptions', ['--dry-run' => true])
            ->assertSuccessful()
            ->expectsOutputToContain('(dry-run)');

        $this->assertDatabaseHas('user_availability_date_exceptions', ['id' => $past->id]);
    }
}
