<?php

declare(strict_types=1);

namespace Tests\Feature\Catalog;

use App\Livewire\Catalog\CatalogOrganizations;
use App\Livewire\Catalog\CatalogPlaces;
use App\Livewire\Catalog\CatalogSeries;
use App\Models\Event;
use App\Models\EventSeries;
use App\Models\Organization;
use App\Models\Place;
use App\Models\User;
use App\Support\Browse\BrowseSearchUrl;
use App\Support\Seo\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
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
            ->assertSeeHtml('data-ui="organization-contact-popover"');
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

        Livewire::withoutLazyLoading()
            ->test(CatalogSeries::class)
            ->set('q', 'Matching Series')
            ->assertSee('Catalog Query Matching Series')
            ->assertDontSee('Catalog Query Other Series');
    }

    public function test_guest_cannot_open_organization_management_index(): void
    {
        $this->get(route('organizations.index'))->assertRedirect(route('login'));
    }
}
