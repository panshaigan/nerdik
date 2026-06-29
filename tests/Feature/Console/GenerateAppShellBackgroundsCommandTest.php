<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class GenerateAppShellBackgroundsCommandTest extends TestCase
{
    #[Test]
    public function command_generates_mobile_and_desktop_variants_for_both_themes(): void
    {
        $this->artisan('app:generate-shell-backgrounds')
            ->assertSuccessful();

        foreach (['dark', 'light'] as $theme) {
            foreach (['384w', '512w', '640w', '768w', '1024w', '1536w', '1716w'] as $suffix) {
                $this->assertFileExists(public_path("images/app/backgrounds/{$theme}/{$suffix}.webp"));
            }

            $this->assertFileDoesNotExist(public_path("images/app/backgrounds/{$theme}/1280w.webp"));
            $this->assertFileDoesNotExist(public_path("images/app/backgrounds/{$theme}/1716w.avif"));
        }

        $manifest = json_decode(
            File::get(public_path('images/app/backgrounds/manifest.json')),
            true,
            flags: JSON_THROW_ON_ERROR,
        );

        $this->assertArrayHasKey('dark', $manifest);
        $this->assertArrayHasKey('light', $manifest);
        $this->assertArrayNotHasKey('fallback', $manifest['dark']);
        $this->assertArrayNotHasKey('avif', $manifest['dark']['variants']);

        $mobileWebpBytes = $manifest['dark']['variants']['webp'][0]['bytes'];
        $desktopWebpBytes = collect($manifest['dark']['variants']['webp'])
            ->firstWhere('width', 1024)['bytes'];

        $this->assertLessThan($desktopWebpBytes, $mobileWebpBytes);
    }
}
