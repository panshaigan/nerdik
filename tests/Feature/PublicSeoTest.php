<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Support\Seo\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PublicSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_home_page_renders_seo_metadata(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('<title>'.Seo::pageTitle((string) __('ui.seo.welcome_title')).'</title>', false);
        $response->assertSee('<meta name="description" content="'.e((string) __('ui.seo.welcome_description')).'">', false);
        $response->assertSee('<link rel="canonical" href="'.e(url('/')).'">', false);
        $response->assertSee('<meta property="og:title" content="'.e(Seo::pageTitle((string) __('ui.seo.welcome_title'))).'">', false);
        $response->assertSee('<meta name="twitter:card" content="summary">', false);
    }

    public function test_search_page_renders_seo_metadata(): void
    {
        $response = $this->get(route('search.index'));

        $response->assertOk();
        $response->assertSee('<title>'.Seo::pageTitle((string) __('ui.browse.search_page_title')).'</title>', false);
        $response->assertSee('<meta name="description" content="'.e((string) __('ui.seo.search_description')).'">', false);
        $response->assertSee('<link rel="canonical" href="'.e(route('search.index')).'">', false);
    }

    public function test_public_event_page_renders_event_seo_metadata(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Neon Con SEO Event',
            'description' => '<p>Discover tabletop sessions and workshops.</p>',
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee('<title>'.Seo::pageTitle('Neon Con SEO Event').'</title>', false);
        $response->assertSee('<link rel="canonical" href="'.e(route('events.show', $event)).'">', false);
        $response->assertSee('Discover tabletop sessions and workshops.', false);
        $response->assertSee('<meta property="og:type" content="article">', false);
    }

    public function test_public_activity_page_is_reachable_without_auth_and_renders_seo_metadata(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(7)->setSecond(0);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
            'name' => 'Neon RPG Session SEO',
            'description' => '<p>Join a one-shot adventure.</p>',
        ]);

        $response = $this->get(route('activities.show', $activity));

        $response->assertOk();
        $response->assertSee('<title>'.Seo::pageTitle('Neon RPG Session SEO').'</title>', false);
        $response->assertSee('<link rel="canonical" href="'.e(route('activities.show', $activity)).'">', false);
        $response->assertSee('Join a one-shot adventure.', false);
    }

    public function test_non_browse_visible_activity_returns_not_found(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'name' => 'Hidden Draft Activity',
        ]);

        $this->get(route('activities.show', $activity))->assertNotFound();
    }
}
