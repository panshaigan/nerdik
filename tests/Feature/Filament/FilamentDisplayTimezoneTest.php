<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\TimeDisplayFormat;
use App\Filament\Admin\Resources\Events\Pages\EditEvent;
use App\Filament\Admin\Resources\Events\Pages\ListEvents;
use App\Models\Event;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FilamentDisplayTimezoneTest extends TestCase
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

    #[Test]
    public function events_table_displays_starts_at_in_admin_timezone_with_twenty_four_hour_format(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwentyFourHour,
        ]);

        Event::factory()->create([
            'name' => 'Timezone Test Event',
            'starts_at' => Carbon::parse('2026-07-01 14:30:00', 'UTC'),
        ]);

        Livewire::actingAs($admin)
            ->test(ListEvents::class)
            ->assertOk()
            ->assertSee('Jul 1, 2026 16:30:00');
    }

    #[Test]
    public function events_table_displays_starts_at_in_admin_timezone_with_twelve_hour_format(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwelveHour,
        ]);

        Event::factory()->create([
            'name' => 'Timezone Twelve Hour Event',
            'starts_at' => Carbon::parse('2026-07-01 14:30:00', 'UTC'),
        ]);

        Livewire::actingAs($admin)
            ->test(ListEvents::class)
            ->assertOk()
            ->assertSee('Jul 1, 2026 04:30:00 PM');
    }

    #[Test]
    public function event_edit_form_displays_starts_at_in_admin_timezone(): void
    {
        $admin = User::factory()->admin()->create();
        $admin->profile()->update([
            'timezone' => 'Europe/Warsaw',
            'time_display_format' => TimeDisplayFormat::TwentyFourHour,
        ]);

        $event = Event::factory()->create([
            'starts_at' => Carbon::parse('2026-07-01 14:30:00', 'UTC'),
        ]);

        Livewire::actingAs($admin)
            ->test(EditEvent::class, ['record' => $event->slug])
            ->assertOk()
            ->assertSee('2026-07-01 16:30', escape: true, stripInitialData: false)
            ->assertDontSee('2026-07-01 14:30', escape: true, stripInitialData: false);
    }
}
