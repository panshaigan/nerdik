<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Seo;

use App\Models\Event;
use App\Models\User;
use App\Support\Seo\SitemapBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class SitemapBuilderTest extends TestCase
{
    use RefreshDatabase;

    public function test_xml_is_cached_when_ttl_is_positive(): void
    {
        Cache::flush();
        config(['seo.sitemap_cache_seconds' => 600]);

        $user = User::factory()->create();
        $cachedEvent = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Cached Sitemap Event',
        ]);

        $builder = app(SitemapBuilder::class);
        $first = $builder->xml();

        $laterEvent = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'After Cache Event',
        ]);

        $second = $builder->xml();

        $this->assertSame($first, $second);
        $this->assertStringContainsString(route('events.show', $cachedEvent), $second);
        $this->assertStringNotContainsString(route('events.show', $laterEvent), $second);
    }

    public function test_xml_skips_cache_when_ttl_is_zero(): void
    {
        Cache::flush();
        config(['seo.sitemap_cache_seconds' => 0]);

        $user = User::factory()->create();
        $firstEvent = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'First Live Event',
        ]);

        $builder = app(SitemapBuilder::class);
        $first = $builder->xml();

        $secondEvent = Event::factory()->public()->create([
            'created_by' => $user->id,
            'name' => 'Second Live Event',
        ]);

        $second = $builder->xml();

        $this->assertStringContainsString(route('events.show', $firstEvent), $first);
        $this->assertStringContainsString(route('events.show', $secondEvent), $second);
    }
}
