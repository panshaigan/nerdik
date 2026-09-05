<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GenerateBrandLogoCommandTest extends TestCase
{
    #[Test]
    public function command_generates_configured_brand_logo_variants(): void
    {
        $this->artisan('app:generate-brand-logo')
            ->assertSuccessful();

        foreach ([40, 48, 64, 80, 96, 128, 160, 192] as $width) {
            $this->assertFileExists(public_path("images/app/brand/{$width}w.webp"));
        }

        $this->assertFileDoesNotExist(public_path('images/app/brand/256w.webp'));

        $manifest = json_decode(
            File::get(public_path('images/app/brand/manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertSame(1271, $manifest['width']);
        $this->assertSame(1180, $manifest['height']);
        $this->assertCount(8, $manifest['variants']['webp']);

        $smallestBytes = $manifest['variants']['webp'][0]['bytes'];
        $largestBytes = $manifest['variants']['webp'][7]['bytes'];

        $this->assertLessThan($largestBytes, $smallestBytes);
    }
}
