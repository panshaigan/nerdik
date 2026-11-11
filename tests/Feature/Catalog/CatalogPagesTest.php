<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Livewire\Catalog\CatalogOrganizations;
use App\Livewire\Catalog\CatalogPlaces;
use App\Livewire\Catalog\CatalogSeries;
use App\Models\Activity;
use App\Models\ActivitySeries;
use App\Models\ActivityUser;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\User;
use App\Support\Browse\BrowseSearchUrl;
use App\Support\Catalog\CatalogSeriesSortStats;
use App\Support\Seo\Seo;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class CatalogPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_can_view_places_catalog_and_rooms_are_hidden(): void
    {
        $venue = Place::factory()->venue()->create([
            'name' => 'Catalog Visible Venue Marker',
            'address' => 'ul. Katalogowa 12',
        ]);
        $room = Place::factory()->room($venue)->create(['name' => 'Catalog Hidden Room Marker']);

        $this->get(route('catalog.places'))
            ->assertOk()
            ->assertSee('Catalog Visible Venue Marker', false)
            ->assertSee('ul. Katalogowa 12', false)
            ->assertDontSee('Catalog Hidden Room Marker', false)
            ->assertSee(BrowseSearchUrl::forPlace($venue), false)
            ->assertSee('<title>'.Seo::pageTitle((string) __('ui.catalog.places_title')).'</title>', false);
    }

    public function test_guest_can_view_organizations_catalog(): void
    {
        $organization = Organization::factory()->create(['name' => 'Catalog Visible Org Marker']);

        $this->get(route('catalog.organizations'))
            ->assertOk()
            ->assertSee('Catalog Visible Org Marker', false)
            ->assertDontSee(BrowseSearchUrl::forOrganization($organization), false)
            ->assertSeeHtml('data-ui="catalog-organization-card"')
            ->assertSee('<title>'.Seo::pageTitle((string) __('ui.catalog.organizations_title')).'</title>', false);
    }

    public function test_organization_catalog_card_opens_preview_popup(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Catalog Preview Org Marker',
            'description' => '<p>Org preview body copy.</p>',
        ]);

        Livewire::withoutLazyLoading()
            ->test(CatalogOrganizations::class)
            ->call('openOrganizationPreview', $organization->id)
            ->assertSet('organizationPreviewModalOpen', true)
            ->assertSet('previewOrganizationId', $organization->id)
            ->assertSeeHtml('data-ui="overlay-sheet"')
            ->assertSeeHtml('data-ui="organization-contact-popover"')
            ->assertSee('Catalog Preview Org Marker', false)
            ->assertSee('Org preview body copy.')
            ->assertSee(__('ui.organizations.events'))
            ->assertDontSeeHtml('data-ui="organization-contact-popover-requests"');
    }

    public function test_organization_catalog_shows_edit_icon_for_owner(): void
    {
        $owner = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'name' => 'Catalog Editable Org Marker',
        ]);
        Organization::factory()->create([
            'name' => 'Catalog Other Org Marker',
        ]);

        Livewire::actingAs($owner)
            ->test(CatalogOrganizations::class)
            ->assertSeeHtml('data-ui="catalog-organization-edit"')
            ->assertSee(route('organizations.edit', $organization), false);
    }

    public function test_organization_catalog_hides_edit_icon_for_guest(): void
    {
        Organization::factory()->create(['name' => 'Catalog Guest Org Marker']);

        Livewire::test(CatalogOrganizations::class)
            ->assertDontSeeHtml('data-ui="catalog-organization-edit"');
    }

    public function test_place_catalog_shows_edit_icon_for_owner(): void
    {
        $owner = User::factory()->create();
        $place = Place::factory()->venue()->create([
            'created_by' => $owner->id,
            'name' => 'Catalog Editable Place Marker',
        ]);
        Place::factory()->venue()->create([
            'name' => 'Catalog Other Place Marker',
        ]);

        Livewire::actingAs($owner)
            ->test(CatalogPlaces::class)
            ->assertSeeHtml('data-ui="catalog-place-edit"')
            ->assertSee(route('places.edit', $place), false);
    }

    public function test_place_catalog_hides_edit_icon_for_guest(): void
    {
        Place::factory()->venue()->create(['name' => 'Catalog Guest Place Marker']);

        Livewire::test(CatalogPlaces::class)
            ->assertDontSeeHtml('data-ui="catalog-place-edit"');
    }

    public function test_guest_can_view_series_catalog_and_non_public_series_are_hidden(): void
    {
        $owner = User::factory()->create();

        $visible = EventSeries::factory()->create([
            'name' => 'Catalog Visible Series Marker',
            'created_by' => $owner->id,
        ]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'organization_id' => null,
            'event_series_id' => $visible->id,
            'name' => 'Visible Series Edition',
        ]);

        $privateOnly = EventSeries::factory()->create([
            'name' => 'Catalog Private Series Marker',
            'created_by' => $owner->id,
        ]);
        Event::factory()->private()->create([
            'created_by' => $owner->id,
            'organization_id' => null,
            'event_series_id' => $privateOnly->id,
            'name' => 'Private Series Edition',
        ]);

        $cancelledOnly = EventSeries::factory()->create([
            'name' => 'Catalog Cancelled Series Marker',
            'created_by' => $owner->id,
        ]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'organization_id' => null,
            'event_series_id' => $cancelledOnly->id,
            'name' => 'Cancelled Series Edition',
            'cancelled_at' => now(),
        ]);

        $this->get(route('catalog.series'))
            ->assertOk()
            ->assertSee('Catalog Visible Series Marker', false)
            ->assertSee(route('event-series.show', $visible), false)
            ->assertDontSee('Catalog Private Series Marker', false)
            ->assertDontSee('Catalog Cancelled Series Marker', false)
            ->assertSeeHtml('data-ui="catalog-series-card"')
            ->assertSee('<title>'.Seo::pageTitle((string) __('ui.catalog.series_title')).'</title>', false);
    }

    public function test_guest_sees_event_and_activity_series_in_one_catalog(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $owner = User::factory()->create();

        $visibleEventSeries = EventSeries::factory()->create([
            'name' => 'Catalog Mixed Event Series Marker',
            'created_by' => $owner->id,
        ]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'organization_id' => null,
            'event_series_id' => $visibleEventSeries->id,
            'name' => 'Mixed Event Edition',
        ]);

        $visible = ActivitySeries::factory()->create([
            'name' => 'Catalog Visible Activity Series Marker',
            'created_by' => $owner->id,
        ]);
        $visibleActivity = Activity::factory()->selfHosted()->create([
            'created_by' => $owner->id,
            'activity_series_id' => $visible->id,
            'name' => 'Visible Activity Session',
        ]);
        $visibleActivity->update([
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(3),
        ]);

        $draftOnly = ActivitySeries::factory()->create([
            'name' => 'Catalog Draft Activity Series Marker',
            'created_by' => $owner->id,
        ]);
        Activity::factory()->create([
            'created_by' => $owner->id,
            'activity_series_id' => $draftOnly->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'name' => 'Draft Activity Session',
        ]);

        Livewire::withoutLazyLoading()
            ->test(CatalogSeries::class)
            ->assertSee('Catalog Mixed Event Series Marker')
            ->assertSee(route('event-series.show', $visibleEventSeries), false)
            ->assertSee('Catalog Visible Activity Series Marker')
            ->assertSee(route('activity-series.show', $visible), false)
            ->assertDontSee('Catalog Draft Activity Series Marker');
    }

    public function test_place_catalog_text_query_filters_by_name(): void
    {
        Place::factory()->venue()->create(['name' => 'Catalog Query Matching Venue']);
        Place::factory()->venue()->create(['name' => 'Catalog Query Other Venue']);

        Livewire::withoutLazyLoading()
            ->test(CatalogPlaces::class)
            ->set('q', 'Matching Venue')
            ->assertSee('Catalog Query Matching Venue')
            ->assertDontSee('Catalog Query Other Venue');
    }

    public function test_organization_catalog_text_query_filters_by_name(): void
    {
        Organization::factory()->create(['name' => 'Catalog Query Matching Org']);
        Organization::factory()->create(['name' => 'Catalog Query Other Org']);

        Livewire::withoutLazyLoading()
            ->test(CatalogOrganizations::class)
            ->set('q', 'Matching Org')
            ->assertSee('Catalog Query Matching Org')
            ->assertDontSee('Catalog Query Other Org');
    }

    public function test_series_catalog_text_query_filters_by_name(): void
    {
        $owner = User::factory()->create();

        $matching = EventSeries::factory()->create([
            'name' => 'Catalog Query Matching Series',
            'created_by' => $owner->id,
        ]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'organization_id' => null,
            'event_series_id' => $matching->id,
        ]);

        $other = EventSeries::factory()->create([
            'name' => 'Catalog Query Other Series',
            'created_by' => $owner->id,
        ]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'organization_id' => null,
            'event_series_id' => $other->id,
        ]);

        $this->seed(ActivityTypeSeeder::class);
        $matchingActivity = ActivitySeries::factory()->create([
            'name' => 'Catalog Query Matching Activity Series',
            'created_by' => $owner->id,
        ]);
        $matchingActivitySession = Activity::factory()->selfHosted()->create([
            'created_by' => $owner->id,
            'activity_series_id' => $matchingActivity->id,
            'name' => 'Matching Activity Session',
        ]);
        $matchingActivitySession->update([
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
        ]);

        Livewire::withoutLazyLoading()
            ->test(CatalogSeries::class)
            ->set('q', 'Matching Series')
            ->assertSee('Catalog Query Matching Series')
            ->assertDontSee('Catalog Query Other Series')
            ->assertDontSee('Catalog Query Matching Activity Series');
    }

    public function test_series_catalog_defaults_to_popularity_sort(): void
    {
        Cache::forget(CatalogSeriesSortStats::CACHE_KEY);
        $this->seed(ActivityTypeSeeder::class);

        $host = User::factory()->create();
        $guestOne = User::factory()->create();
        $guestTwo = User::factory()->create();

        $popular = ActivitySeries::factory()->create([
            'name' => 'Catalog Sort Popular Series',
            'created_by' => $host->id,
        ]);
        $quiet = ActivitySeries::factory()->create([
            'name' => 'Catalog Sort Quiet Series',
            'created_by' => $host->id,
        ]);

        $popularActivity = Activity::factory()->selfHosted()->create([
            'created_by' => $host->id,
            'activity_series_id' => $popular->id,
        ]);
        $popularActivity->update([
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(3)->addHours(2),
        ]);

        Activity::factory()->selfHosted()->create([
            'created_by' => $host->id,
            'activity_series_id' => $quiet->id,
        ])->update([
            'starts_at' => now()->addDays(4),
            'ends_at' => now()->addDays(4)->addHours(2),
        ]);

        ActivityUser::query()->create([
            'activity_id' => $popularActivity->id,
            'user_id' => $guestOne->id,
            'is_absent' => false,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $popularActivity->id,
            'user_id' => $guestTwo->id,
            'is_absent' => false,
        ]);

        Livewire::withoutLazyLoading()
            ->test(CatalogSeries::class)
            ->assertSet('sort', 'popularity')
            ->assertSeeInOrder(['Catalog Sort Popular Series', 'Catalog Sort Quiet Series']);
    }

    public function test_series_catalog_can_sort_by_name_and_activities(): void
    {
        Cache::forget(CatalogSeriesSortStats::CACHE_KEY);
        $this->seed(ActivityTypeSeeder::class);
        $owner = User::factory()->create();

        $alpha = EventSeries::factory()->create([
            'name' => 'Catalog Sort Alpha Series',
            'created_by' => $owner->id,
        ]);
        Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $alpha->id,
            'starts_at' => now()->addDays(10),
            'ends_at' => now()->addDays(10)->addHours(3),
        ]);

        $zebra = EventSeries::factory()->create([
            'name' => 'Catalog Sort Zebra Series',
            'created_by' => $owner->id,
        ]);
        $zebraEvent = Event::factory()->public()->create([
            'created_by' => $owner->id,
            'event_series_id' => $zebra->id,
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(5)->addHours(3),
        ]);

        $activityOne = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $activityTwo = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        Slot::factory()->create([
            'event_id' => $zebraEvent->id,
            'activity_id' => $activityOne->id,
        ]);
        Slot::factory()->create([
            'event_id' => $zebraEvent->id,
            'activity_id' => $activityTwo->id,
        ]);

        Livewire::withoutLazyLoading()
            ->test(CatalogSeries::class)
            ->set('sort', 'name')
            ->assertSeeInOrder(['Catalog Sort Alpha Series', 'Catalog Sort Zebra Series'])
            ->set('sort', 'activities')
            ->assertSeeInOrder(['Catalog Sort Zebra Series', 'Catalog Sort Alpha Series']);
    }

    public function test_guest_cannot_open_organization_management_index(): void
    {
        $this->get(route('organizations.index'))->assertRedirect(route('login'));
    }
}
