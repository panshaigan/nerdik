<?php

declare(strict_types=1);

namespace Tests\Unit\Config;

use App\Models\TagCategory;
use Tests\TestCase;

class BrowseTagSuggestionsConfigTest extends TestCase
{
    public function test_category_order_matches_expected_browse_sequence(): void
    {
        $order = config('browse.tag_suggestions.category_order');

        $this->assertSame([
            TagCategory::KEY_GAME,
            TagCategory::KEY_SETTING,
            TagCategory::KEY_GENRE,
            TagCategory::KEY_FORMAT,
            TagCategory::KEY_OTHER,
            TagCategory::KEY_MECHANIC,
            TagCategory::KEY_TOPIC,
            TagCategory::KEY_TRIGGER,
        ], $order);
    }

    public function test_trigger_is_hidden_on_empty_search(): void
    {
        $hidden = config('browse.tag_suggestions.hidden_category_keys_on_empty_search');

        $this->assertSame([TagCategory::KEY_TRIGGER], $hidden);
    }

    public function test_max_per_category_is_seven(): void
    {
        $this->assertSame(7, config('browse.tag_suggestions.max_per_category'));
    }
}
