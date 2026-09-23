<?php

declare(strict_types=1);

namespace Tests\Unit\Deployment;

use PHPUnit\Framework\TestCase;

final class ProductionNginxConfigTest extends TestCase
{
    public function test_fingerprinted_build_assets_are_cached_immutably(): void
    {
        $block = $this->locationBlock('location ^~ /build/assets/');

        $this->assertStringContainsString(
            'Cache-Control "public, max-age=31536000, immutable"',
            $block,
        );
    }

    public function test_regenerable_images_are_cached_without_immutable(): void
    {
        $mediaBlock = $this->locationBlock('location ~* ^/storage/media/');
        $this->assertStringContainsString('max-age=2592000', $mediaBlock);
        $this->assertStringNotContainsString('immutable', $mediaBlock);

        $imageBlock = $this->locationBlock('location ~* ^/images/app/');
        $this->assertStringContainsString('max-age=2592000', $imageBlock);
        $this->assertStringNotContainsString('immutable', $imageBlock);
    }

    private function locationBlock(string $location): string
    {
        $configuration = file_get_contents(
            dirname(__DIR__, 3).'/docker/production/nginx/default.conf',
        );

        $this->assertIsString($configuration);

        $start = strpos($configuration, $location);
        $this->assertNotFalse($start);

        $block = substr($configuration, $start);
        $end = strpos($block, '}');
        $this->assertNotFalse($end);

        return substr($block, 0, $end + 1);
    }
}
