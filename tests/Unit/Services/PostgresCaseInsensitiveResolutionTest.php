<?php

namespace Tests\Unit\Services;

use App\Models\Country;
use App\Models\Tag;
use App\Services\LocationResolver;
use App\Services\TagSelectionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostgresCaseInsensitiveResolutionTest extends TestCase
{
    use RefreshDatabase;

    public function test_tag_selection_service_reuses_existing_tag_case_insensitively(): void
    {
        $tag = Tag::factory()->create();
        $tag->translations()->create([
            'locale' => 'en',
            'label' => 'Board Games',
            'slug' => 'board-games',
        ]);

        $service = app(TagSelectionService::class);
        $resolvedTagId = $service->findOrCreateTagByLabel('board games', (int) $tag->tag_category_id);

        $this->assertSame($tag->id, $resolvedTagId);
    }

    public function test_location_resolver_reuses_existing_city_case_insensitively(): void
    {
        $country = Country::factory()->create([
            'iso_alpha2' => 'PL',
        ]);
        $city = $country->cities()->create();
        $city->translations()->create([
            'locale' => 'en',
            'name' => 'Warsaw',
        ]);

        $resolver = app(LocationResolver::class);
        $resolvedCityId = $resolver->findOrCreateCityId($country->id, 'warsaw');

        $this->assertSame($city->id, $resolvedCityId);
        $this->assertSame(1, $country->cities()->count());
    }
}
