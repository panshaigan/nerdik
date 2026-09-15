<?php

namespace Tests\Feature;

use Tests\TestCase;

class BrowseMapsInitBootTest extends TestCase
{
    public function test_browse_map_boot_does_not_call_catch_on_sync_init_return(): void
    {
        $source = (string) file_get_contents(resource_path('js/maps-init.js'));

        $this->assertStringNotContainsString(
            'initBrowseEventsMap().catch',
            $source,
            'initBrowseEventsMap() is synchronous; .catch on its return value throws TypeError in the browser.',
        );

        $this->assertMatchesRegularExpression(
            '/import\(\s*[\'"]\.\/maps\/browse-events-map\.js[\'"]\s*\)\s*\.then\s*\(/',
            $source,
        );

        $this->assertMatchesRegularExpression(
            '/import\(\s*[\'"]\.\/maps\/browse-events-map\.js[\'"]\s*\)[\s\S]*?\.catch\s*\(/',
            $source,
            'Import failures should still be handled on the dynamic import promise.',
        );
    }

    public function test_init_browse_events_map_is_synchronous(): void
    {
        $source = (string) file_get_contents(resource_path('js/maps/browse-events-map.js'));

        $this->assertMatchesRegularExpression(
            '/export\s+function\s+initBrowseEventsMap\s*\(/',
            $source,
        );
        $this->assertDoesNotMatchRegularExpression(
            '/export\s+async\s+function\s+initBrowseEventsMap\s*\(/',
            $source,
        );
    }
}
