<?php

declare(strict_types=1);

namespace Tests\Feature\Browse;

use App\Enums\ActivityLogoSource;
use App\Enums\EventLogoSource;
use App\Livewire\Activities\ManageActivityForm;
use App\Livewire\Browse\BrowseEvents;
use App\Livewire\Events\ManageEventForm;
use App\Models\Event;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BrowseEventsPaginationLoadingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function browse_events_listings_include_pagination_loading_overlay_markup(): void
    {
        $owner = User::factory()->create();
        $startsAt = now()->addDays(5)->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(4);

        for ($i = 1; $i <= 13; $i++) {
            Event::factory()->public()->create([
                'created_by' => $owner->id,
                'name' => sprintf('Browse Paginate Event %02d', $i),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
            ]);
        }

        Livewire::withoutLazyLoading()
            ->test(BrowseEvents::class)
            ->set('only_events', true)
            ->assertSee('Browse Paginate Event 01')
            ->assertSee('Browse Paginate Event 12')
            ->assertDontSee('Browse Paginate Event 13')
            ->assertSeeHtml('data-ui="browse-events-listings-loading"')
            ->call('gotoPage', 2)
            ->assertSee('Browse Paginate Event 13')
            ->assertDontSee('Browse Paginate Event 01');
    }

    #[Test]
    public function manage_activity_form_image_tab_includes_logo_source_loading_overlay(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('tab', 'image')
            ->assertSeeHtml('data-ui="manage-image-source-loading"')
            ->set('logo_source', ActivityLogoSource::Upload->value)
            ->assertSet('logo_source', ActivityLogoSource::Upload->value);
    }

    #[Test]
    public function manage_event_form_image_tab_includes_logo_source_loading_overlay(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('tab', 'image')
            ->assertSeeHtml('data-ui="manage-image-source-loading"')
            ->set('logo_source', EventLogoSource::Upload->value)
            ->assertSet('logo_source', EventLogoSource::Upload->value);
    }
}
