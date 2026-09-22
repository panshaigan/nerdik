<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Livewire\Activities\ShowActivity;
use App\Livewire\Events\EventShowPlanTab;
use App\Livewire\Events\ShowEvent;
use App\Livewire\Events\ShowEventSeries;
use App\Models\Activity;
use App\Models\EntityLink;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EntityLinksShowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function activity_manage_menu_exposes_add_link_for_host(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $html = Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->html();

        $this->assertStringContainsString('data-ui="activity-show-add-link"', $html);
        $this->assertStringContainsString('data-ui="activity-show-edit"', $html);
    }

    #[Test]
    public function activity_manage_menu_exposes_add_link_for_parent_event_organizer_without_edit(): void
    {
        $host = User::factory()->create();
        $organizer = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $organizer->id,
            'updated_by' => $organizer->id,
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
        ]);
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'created_by' => $organizer->id,
        ]);

        $html = Livewire::actingAs($organizer)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->html();

        $this->assertStringContainsString('data-ui="activity-show-manage"', $html);
        $this->assertStringContainsString('data-ui="activity-show-add-link"', $html);
        $this->assertStringNotContainsString('data-ui="activity-show-edit"', $html);
    }

    #[Test]
    public function activity_about_tab_shows_saved_links(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'description' => 'About this game',
        ]);
        EntityLink::factory()->create([
            'linkable_type' => 'activity',
            'linkable_id' => $activity->id,
            'name' => 'Character sheet',
            'url' => 'https://example.com/sheet',
        ]);

        Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->assertSee('Character sheet')
            ->assertSee('https://example.com/sheet', false);
    }

    #[Test]
    public function event_manage_menu_exposes_add_link_and_plan_shows_links(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        EntityLink::factory()->create([
            'linkable_type' => 'event',
            'linkable_id' => $event->id,
            'name' => 'Facebook group',
            'url' => 'https://example.com/fb',
        ]);

        $html = Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->html();

        $this->assertStringContainsString('data-ui="event-show-add-link"', $html);

        Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, [
                'eventId' => $event->id,
                'activeTab' => 'plan',
            ])
            ->assertSee('Facebook group')
            ->assertSeeHtml('data-ui="event-show-entity-links"');
    }

    #[Test]
    public function event_series_manage_menu_and_header_show_links(): void
    {
        $owner = User::factory()->create();
        $series = EventSeries::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        Event::factory()->create([
            'event_series_id' => $series->id,
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
            'is_public' => true,
        ]);
        EntityLink::factory()->create([
            'linkable_type' => 'event_series',
            'linkable_id' => $series->id,
            'name' => 'Series Discord',
            'url' => 'https://example.com/discord',
        ]);

        $html = Livewire::actingAs($owner)
            ->test(ShowEventSeries::class, ['eventSeries' => $series])
            ->html();

        $this->assertStringContainsString('data-ui="event-series-show-add-link"', $html);
        $this->assertStringContainsString('Series Discord', $html);
    }

    #[Test]
    public function open_add_entity_link_dispatches_to_manage_component(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        Livewire::actingAs($host)
            ->test(ShowActivity::class, ['activity' => $activity])
            ->call('openAddEntityLink')
            ->assertDispatched(
                'open-add-entity-link',
                key: $activity->getMorphClass().'-'.$activity->id,
            );
    }
}
