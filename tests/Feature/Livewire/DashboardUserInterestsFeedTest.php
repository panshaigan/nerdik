<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Dashboard\Dashboard;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class DashboardUserInterestsFeedTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_feed_includes_upcoming_event_marked_as_interest(): void
    {
        $viewer = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'name' => 'Dashboard Interest Event Alpha',
            'created_by' => $organizer->id,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $viewer->interestedEvents()->attach($event->id);

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSee('Dashboard Interest Event Alpha');
    }

    public function test_dashboard_feed_includes_upcoming_activity_marked_as_interest(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'name' => 'Dashboard Interest Activity Beta',
            'created_by' => $host->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(3),
        ]);

        $viewer->interestedActivities()->attach($activity->id);

        Livewire::actingAs($viewer)
            ->test(Dashboard::class)
            ->assertSee('Dashboard Interest Activity Beta');
    }

    public function test_event_preview_host_badge_uses_preview_livewire_key(): void
    {
        $viewer = User::factory()->create();
        $host = User::factory()->create(['nickname' => 'Rynek58']);
        $event = Event::factory()->public()->create([
            'name' => 'Porzucane I',
            'created_by' => $host->id,
            'organization_id' => null,
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);

        $viewer->interestedEvents()->attach($event->id);

        $eventId = (int) $event->id;
        $hostId = (int) $host->id;
        $listingKey = 'user-badge-contact-'.$hostId.'-0-0-late-0-listing-event-'.$eventId;
        $previewKey = 'user-badge-contact-'.$hostId.'-0-0-late-0-listing-event-preview-'.$eventId;

        Livewire::withoutLazyLoading()
            ->actingAs($viewer)
            ->test(Dashboard::class)
            ->assertDontSeeHtml('wire:key="'.$listingKey.'"')
            ->call('openListingEventPreview', $eventId)
            ->assertSet('eventPreviewModalOpen', true)
            ->assertSeeHtml('data-ui="listing-event-preview-host"')
            ->assertSeeHtml('wire:key="'.$previewKey.'"');
    }
}
