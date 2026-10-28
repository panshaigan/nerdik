<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Actions\Images\GenerateAppShellBackgroundVariants;
use Illuminate\Support\Facades\File;
use PHPUnit\Framework\Attributes\Group;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

#[Group('shell-background-mutation')]
#[Group('slow')]
final class GenerateAppShellBackgroundsCommandTest extends TestCase
{
    private ?string $isolatedOutputDir = null;

    #[Test]
    public function command_generates_mobile_and_desktop_variants_for_both_themes(): void
    {
        $this->isolatedOutputDir = 'images/app/backgrounds/test-output-'.getmypid();

        config([
            'media.app_shell_background.output_dir' => $this->isolatedOutputDir,
        ]);

        $this->artisan('app:generate-shell-backgrounds')
            ->assertSuccessful();

        $absoluteOutputDir = public_path($this->isolatedOutputDir);

        foreach (['dark', 'light'] as $theme) {
            foreach (['384w', '512w', '640w', '768w', '1024w', '1536w', '1716w'] as $suffix) {
                $this->assertFileExists("{$absoluteOutputDir}/{$theme}/{$suffix}.webp");
            }

            $this->assertFileDoesNotExist("{$absoluteOutputDir}/{$theme}/1280w.webp");
            $this->assertFileDoesNotExist("{$absoluteOutputDir}/{$theme}/1716w.avif");
        }

        $manifest = json_decode(
            File::get("{$absoluteOutputDir}/manifest.json"),
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

    #[Test]
    public function command_refuses_to_overwrite_live_background_assets_during_tests(): void
    {
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Refusing to regenerate app shell backgrounds');

        app(GenerateAppShellBackgroundVariants::class)();
    }

    protected function tearDown(): void
    {
        if ($this->isolatedOutputDir !== null) {
            File::deleteDirectory(public_path($this->isolatedOutputDir));
            $this->isolatedOutputDir = null;
        }

        parent::tearDown();
    }
}
