<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Features;

use App\Support\Features\FeatureCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeatureCatalogTest extends TestCase
{
    #[Test]
    public function catalog_includes_expected_item_ids(): void
    {
        $ids = FeatureCatalog::allItemIds();

        $expected = [
            'everyone_locales',
            'players_search_map_list',
            'players_open_join',
            'players_groups_calendar',
            'players_play_preferences',
            'hosts_activity_types',
            'hosts_roster_pdf',
            'organizers_enrollment_windows',
            'organizers_schedule_activity',
            'organizers_event_pdf',
        ];

        foreach ($expected as $id) {
            $this->assertContains($id, $ids, "Missing catalog item: {$id}");
        }
    }

    #[Test]
    public function valid_tab_keys_match_tabs(): void
    {
        $this->assertSame(['players', 'hosts', 'organizers'], FeatureCatalog::validTabKeys());
    }

    #[Test]
    public function every_catalog_copy_key_exists_in_english_and_polish(): void
    {
        $suffixes = [];

        foreach (FeatureCatalog::everyoneItems() as $item) {
            $suffixes[] = $item['title_key'];
            $suffixes[] = $item['body_key'];
        }

        foreach (FeatureCatalog::tabs() as $tab) {
            $suffixes[] = $tab['label_key'];
            $suffixes[] = $tab['intro_key'];

            foreach ($tab['groups'] as $group) {
                $suffixes[] = 'group_'.$group['group_key'];

                foreach ($group['items'] as $item) {
                    $suffixes[] = $item['title_key'];
                    $suffixes[] = $item['body_key'];
                }
            }
        }

        foreach (array_unique($suffixes) as $suffix) {
            $key = FeatureCatalog::langKey($suffix);
            $this->assertNotSame($key, __($key, [], 'en'), "Missing English translation: {$key}");
            $this->assertNotSame($key, __($key, [], 'pl'), "Missing Polish translation: {$key}");
        }
    }
}
