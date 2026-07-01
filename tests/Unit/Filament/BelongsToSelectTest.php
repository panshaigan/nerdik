<?php

declare(strict_types=1);

namespace Tests\Unit\Filament;

use App\Filament\Forms\Components\BelongsToSelect;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\City;
use App\Models\Country;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Models\User;
use App\Support\Filament\FilamentRecordLabel;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BelongsToSelectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityTypeSeeder::class);
    }

    #[Test]
    public function make_preset_is_searchable_and_preloads_initial_suggestions(): void
    {
        $select = BelongsToSelect::make('place_id', 'place');

        $this->assertTrue($select->isSearchable());
        $this->assertTrue($select->isPreloaded());
        $this->assertSame(20, $select->getOptionsLimit());
    }

    #[Test]
    public function activity_type_select_returns_initial_suggestions_without_search(): void
    {
        $select = BelongsToSelect::activityType('activity_type_id', relationship: null);

        $results = $select->getSearchResults('');

        $this->assertNotEmpty($results);
        $this->assertLessThanOrEqual(20, count($results));
    }

    #[Test]
    public function user_preset_is_searchable(): void
    {
        $select = BelongsToSelect::user('created_by');

        $this->assertTrue($select->isSearchable());
    }

    #[Test]
    public function tag_display_label_uses_translation_with_locale_fallback(): void
    {
        $tag = Tag::factory()->create();
        $tag->translations()->create([
            'locale' => 'en',
            'label' => 'English Label',
        ]);

        $this->assertSame('English Label', $tag->displayLabel('en'));
        $this->assertSame('English Label', $tag->displayLabel('pl'));
    }

    #[Test]
    public function tag_display_label_falls_back_to_id_when_no_translation(): void
    {
        $tag = Tag::factory()->create();

        $this->assertSame('#'.$tag->getKey(), $tag->displayLabel());
    }

    #[Test]
    public function filament_record_label_resolves_user_nickname(): void
    {
        $user = User::factory()->create(['nickname' => 'admin_handle']);

        $this->assertSame('admin_handle', FilamentRecordLabel::for($user));
    }

    #[Test]
    public function filament_record_label_resolves_activity_proposal_composite(): void
    {
        $proposal = ActivityProposal::factory()->create();
        $proposal->load(['activity', 'event']);

        $label = FilamentRecordLabel::activityProposal($proposal);

        $this->assertStringContainsString('#'.$proposal->getKey(), $label);
        $this->assertStringContainsString($proposal->activity->name, $label);
        $this->assertStringContainsString($proposal->event->name, $label);
    }

    #[Test]
    public function filament_record_label_resolves_country_name(): void
    {
        $country = Country::factory()->create(['iso_alpha2' => 'PL']);
        $country->translations()->create([
            'locale' => 'en',
            'name' => 'Poland',
        ]);

        $this->assertSame('Poland', FilamentRecordLabel::for($country));
    }

    #[Test]
    public function filament_record_label_resolves_city_name(): void
    {
        $country = Country::factory()->create(['iso_alpha2' => 'PL']);
        $city = City::factory()->create([
            'slug' => 'warsaw',
            'country_id' => $country->id,
        ]);
        $city->translations()->create([
            'locale' => 'en',
            'name' => 'Warsaw',
        ]);

        $this->assertSame('Warsaw', FilamentRecordLabel::for($city));
    }

    #[Test]
    public function filament_record_label_resolves_tag_category_name(): void
    {
        $category = TagCategory::factory()->create(['key' => 'game']);
        $category->translations()->create([
            'locale' => 'en',
            'label' => 'Game',
        ]);

        $this->assertSame('Game', FilamentRecordLabel::for($category));
    }

    #[Test]
    public function filament_record_label_resolves_activity_type_slug(): void
    {
        $activityType = ActivityType::findBySlug(ActivityType::SLUG_RPG);

        $this->assertSame(ActivityType::SLUG_RPG, FilamentRecordLabel::for($activityType));
    }
}
