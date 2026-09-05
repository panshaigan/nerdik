<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Images\GenerateBrandLogoVariants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-brand-logo')]
#[Description('Generate responsive WebP variants for the brand logo from the source WebP')]
final class GenerateBrandLogoCommand extends Command
{
    public function handle(GenerateBrandLogoVariants $generateVariants): int
    {
        $manifest = $generateVariants();

        $variantCount = count($manifest['variants']['webp'] ?? []);

        $this->info(sprintf('Generated %d brand logo WebP variant(s).', $variantCount));
        $this->line('Manifest: public/images/app/brand/manifest.json');

        return self::SUCCESS;
    }
}
