<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Livewire\EntityLinks\ManageEntityLinks;
use App\Models\Activity;
use App\Models\EntityLink;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class EntityLinksTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function owner_can_create_link_on_event(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ManageEntityLinks::class, ['linkable' => $event])
            ->set('linkName', 'Discord')
            ->set('linkUrl', 'https://discord.gg/example')
            ->call('saveLink')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entity_links', [
            'linkable_type' => 'event',
            'linkable_id' => $event->id,
            'name' => 'Discord',
            'url' => 'https://discord.gg/example',
        ]);
    }

    #[Test]
    public function stranger_cannot_create_link_on_event(): void
    {
        $owner = User::factory()->create();
        $stranger = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($stranger)
            ->test(ManageEntityLinks::class, ['linkable' => $event])
            ->set('linkName', 'Discord')
            ->set('linkUrl', 'https://discord.gg/example')
            ->call('saveLink')
            ->assertForbidden();

        $this->assertDatabaseCount('entity_links', 0);
    }

    #[Test]
    public function admin_can_manage_links_on_any_entity(): void
    {
        $owner = User::factory()->create();
        $admin = User::factory()->create(['is_admin' => true]);
        $series = EventSeries::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ManageEntityLinks::class, ['linkable' => $series])
            ->set('linkName', 'Series info')
            ->set('linkUrl', 'https://example.com/series')
            ->call('saveLink')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entity_links', [
            'linkable_type' => 'event_series',
            'linkable_id' => $series->id,
            'name' => 'Series info',
        ]);
    }

    #[Test]
    public function activity_host_can_manage_activity_links(): void
    {
        $host = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        Livewire::actingAs($host)
            ->test(ManageEntityLinks::class, ['linkable' => $activity])
            ->set('linkName', 'Rules')
            ->set('linkUrl', 'https://example.com/rules')
            ->call('saveLink')
            ->assertHasNoErrors();

        $this->assertTrue($host->canManageEntityLinks($activity));
    }

    #[Test]
    public function parent_event_organizer_can_manage_activity_links(): void
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

        $activity->load('slot.event');

        $this->assertTrue($organizer->canManageEntityLinks($activity));
        $this->assertFalse($organizer->canModifyEntity($activity));

        Livewire::actingAs($organizer)
            ->test(ManageEntityLinks::class, ['linkable' => $activity->fresh()])
            ->set('linkName', 'Table notes')
            ->set('linkUrl', 'https://example.com/table')
            ->call('saveLink')
            ->assertHasNoErrors();
    }

    #[Test]
    public function place_and_organization_owners_can_manage_links(): void
    {
        $owner = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ManageEntityLinks::class, ['linkable' => $place])
            ->set('linkName', 'Venue map')
            ->set('linkUrl', 'https://example.com/venue')
            ->call('saveLink')
            ->assertHasNoErrors();

        Livewire::actingAs($owner)
            ->test(ManageEntityLinks::class, ['linkable' => $organization])
            ->set('linkName', 'Org site')
            ->set('linkUrl', 'https://example.com/org')
            ->call('saveLink')
            ->assertHasNoErrors();

        $this->assertDatabaseCount('entity_links', 2);
    }

    #[Test]
    public function rejects_non_http_urls_and_supports_update_and_delete(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(ManageEntityLinks::class, ['linkable' => $event])
            ->set('linkName', 'Bad')
            ->set('linkUrl', 'ftp://example.com/file')
            ->call('saveLink')
            ->assertHasErrors(['linkUrl']);

        Livewire::actingAs($owner)
            ->test(ManageEntityLinks::class, ['linkable' => $event])
            ->set('linkName', 'Bare domain')
            ->set('linkUrl', 'example.com/path')
            ->call('saveLink')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entity_links', [
            'linkable_type' => 'event',
            'linkable_id' => $event->id,
            'name' => 'Bare domain',
            'url' => 'https://example.com/path',
        ]);

        $link = EntityLink::factory()->create([
            'linkable_type' => 'event',
            'linkable_id' => $event->id,
            'name' => 'Old',
            'url' => 'https://example.com/old',
        ]);

        Livewire::actingAs($owner)
            ->test(ManageEntityLinks::class, ['linkable' => $event])
            ->call('openEditLinkModal', $link->id)
            ->set('linkName', 'New')
            ->set('linkUrl', 'https://example.com/new')
            ->call('saveLink')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('entity_links', [
            'id' => $link->id,
            'name' => 'New',
            'url' => 'https://example.com/new',
        ]);

        Livewire::actingAs($owner)
            ->test(ManageEntityLinks::class, ['linkable' => $event])
            ->call('confirmDeleteLink', $link->id)
            ->call('deleteLink')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('entity_links', ['id' => $link->id]);
    }

    #[Test]
    public function legacy_place_links_column_is_migrated_away(): void
    {
        $this->assertFalse(Schema::hasColumn('places', 'links'));
    }
}
