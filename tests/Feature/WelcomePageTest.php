<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Actions\Seeders\AttachTagMediaFromPublic;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\TagTranslation;
use App\Models\User;
use App\Services\Welcome\WelcomePageDataService;
use App\Services\Welcome\WelcomeUpcomingQueryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class WelcomePageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget('welcome.platform_stats');
        Cache::forget('welcome.hero_tag_image');
        Cache::forget('welcome.upcoming_listing_ids.6');
    }

    #[Test]
    public function test_home_page_uses_invitation_first_content_without_technical_footer_details(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Discover nerdy activities near you, or create your own events', false);
        $response->assertSee('Closest activities &amp; events', false);
        $response->assertSee('Bring scattered, improvised sign-ups into one place', false);
        $response->assertSee('How it works', false);
        $response->assertSee('Ready to find your next session?', false);
        $response->assertDontSee('Laravel v', false);
        $response->assertDontSee('PHP v', false);
    }

    #[Test]
    public function test_home_page_shows_nearest_upcoming_public_listings_only(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();

        $upcomingEvent = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Aurora Convention',
            'starts_at' => now()->addDays(3),
            'ends_at' => now()->addDays(4),
        ]);
        $upcomingEvent->places()->sync([$place->id]);

        Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => now()->addDays(2),
            'ends_at' => now()->addDays(2)->addHours(2),
            'name' => 'Starlight One-Shot',
        ]);

        Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Past Gathering',
            'starts_at' => now()->subDays(7),
            'ends_at' => now()->subDays(6),
        ]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Aurora Convention', false);
        $response->assertSee('Starlight One-Shot', false);
        $response->assertDontSee('Past Gathering', false);
        $response->assertSee(route('search.index'), false);
        $response->assertSee(route('events.show', $upcomingEvent), false);
    }

    #[Test]
    public function test_home_page_shows_platform_stats(): void
    {
        User::factory()->count(2)->create();
        $host = User::factory()->create();
        $place = Place::factory()->venue()->create();

        Event::factory()->public()->create([
            'created_by' => $host->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        Activity::factory()->create([
            'created_by' => $host->id,
            'updated_by' => $host->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $stats = app(WelcomePageDataService::class)->data()['stats'];
        $expectedUsers = User::query()->where('is_deleted', false)->count();

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('data-ui="welcome-platform-stats"', false);
        $response->assertSee('Members', false);
        $response->assertSee('Happening soon', false);
        $response->assertSee('Happening now', false);
        $response->assertSee((string) $stats->usersCount, false);
        $response->assertSee((string) $stats->upcomingListingsCount, false);
        $response->assertSee((string) $stats->ongoingListingsCount, false);
        $this->assertSame($expectedUsers, $stats->usersCount);
        $this->assertSame(1, $stats->upcomingListingsCount);
        $this->assertSame(1, $stats->ongoingListingsCount);
    }

    #[Test]
    public function test_home_page_renders_hero_tag_image_when_available(): void
    {
        $category = TagCategory::factory()->create(['key' => TagCategory::KEY_GAME]);
        $tag = Tag::factory()->create(['tag_category_id' => $category->id]);
        TagTranslation::factory()->create([
            'tag_id' => $tag->id,
            'locale' => 'en',
            'label' => 'Hero Tag Label',
        ]);

        $fixture = 'images/tag-game/welcome-hero.jpg';
        copy(base_path('tests/fixtures/tag-sample.jpg'), public_path($fixture));
        app(AttachTagMediaFromPublic::class)($tag, [$fixture]);

        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<picture', false);
        $response->assertDontSee('absolute bottom-4 left-4 rounded-lg bg-base-100/90', false);
    }

    #[Test]
    public function test_upcoming_listings_query_is_cached_within_ttl(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();

        Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Cached Convention',
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);
        $event = Event::query()->where('name', 'Cached Convention')->firstOrFail();
        $event->places()->sync([$place->id]);

        $service = app(WelcomeUpcomingQueryService::class);

        DB::enableQueryLog();
        $service->nearestPublicListings();
        $firstCallQueries = count(DB::getQueryLog());

        DB::flushQueryLog();
        $service->nearestPublicListings();
        $secondCallQueries = count(DB::getQueryLog());
        DB::disableQueryLog();

        $this->assertGreaterThan($secondCallQueries, $firstCallQueries);
    }

    #[Test]
    public function test_welcome_locale_links_match_main_navigation_styling(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('ui-nav-locale', false);
        $response->assertSee('wire:navigate', false);
    }

    #[Test]
    public function test_welcome_page_renders_mobile_navigation_drawer_markup(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('aria-controls="mobile-welcome-nav-drawer"', false);
        $response->assertSee('id="mobile-welcome-nav-drawer"', false);
        $response->assertSee(route('login'), false);
        $response->assertSee(route('register'), false);
        $response->assertSee(__('ui.nav.browse_events'), false);
        $response->assertSee('ui-nav-locale', false);
    }
}
