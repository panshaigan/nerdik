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
            'hosts_activity_types',
            'hosts_roster_pdf',
            'organizers_enrollment_windows',
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
}
