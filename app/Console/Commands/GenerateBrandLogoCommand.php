<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Images\GenerateBrandLogoVariants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-brand-logo')]
#[Description('Generate responsive WebP variants and browser icons from the brand logo source WebP')]
final class GenerateBrandLogoCommand extends Command
{
    public function handle(GenerateBrandLogoVariants $generateVariants): int
    {
        $manifest = $generateVariants();

        $variantCount = count($manifest['variants']['webp'] ?? []);
        $icons = is_array($manifest['icons'] ?? null) ? $manifest['icons'] : [];

        $this->info(sprintf('Generated %d brand logo WebP variant(s).', $variantCount));
        $this->line('Manifest: public/images/app/brand/manifest.json');

        if (($icons['favicon_ico'] ?? '') !== '') {
            $this->info('Generated browser icons:');
            $this->line('  - public/'.(string) $icons['favicon_ico']);
            $this->line('  - public/'.(string) $icons['favicon_svg']);
            $this->line('  - public/'.(string) $icons['apple_touch_icon']);
        }

        return self::SUCCESS;
    }
}
