<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class PublicSitemapTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
        config(['seo.sitemap_cache_seconds' => 0]);
    }

    public function test_sitemap_includes_static_and_public_listing_urls(): void
    {
        $user = User::factory()->create();
        $publicEvent = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Public Sitemap Event',
        ]);
        $privateEvent = Event::factory()->private()->create([
            'created_by' => $user->id,
            'name' => 'Private Sitemap Event',
        ]);

        $place = Place::factory()->venue()->create();
        $startsAt = now()->addDays(3)->setSecond(0);
        $publicActivity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => (clone $startsAt)->addHours(2),
            'name' => 'Public Sitemap Activity',
        ]);
        $draftActivity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'name' => 'Draft Sitemap Activity',
        ]);

        $response = $this->get(route('sitemap'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'application/xml; charset=UTF-8');
        $response->assertSee('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', false);
        $response->assertSee('<loc>'.e(url('/')).'</loc>', false);
        $response->assertSee('<loc>'.e(route('search.index')).'</loc>', false);
        $response->assertSee('<loc>'.e(route('privacy')).'</loc>', false);
        $response->assertSee('<loc>'.e(route('terms')).'</loc>', false);
        $response->assertSee('<loc>'.e(route('contact')).'</loc>', false);
        $response->assertSee('<loc>'.e(route('events.show', $publicEvent)).'</loc>', false);
        $response->assertSee('<loc>'.e(route('activities.show', $publicActivity)).'</loc>', false);
        $response->assertDontSee(route('events.show', $privateEvent), false);
        $response->assertDontSee(route('activities.show', $draftActivity), false);
    }

    public function test_robots_txt_disallows_auth_paths_and_points_at_sitemap(): void
    {
        $response = $this->get(route('robots'));

        $response->assertOk();
        $response->assertHeader('Content-Type', 'text/plain; charset=UTF-8');
        $response->assertSee("User-agent: *\n", false);
        $response->assertSee("Disallow: /login\n", false);
        $response->assertSee("Disallow: /register\n", false);
        $response->assertSee("Disallow: /auth/\n", false);
        $response->assertSee("User-agent: Amazonbot\n", false);
        $response->assertSee('Sitemap: '.url('/sitemap.xml'), false);
    }
}
