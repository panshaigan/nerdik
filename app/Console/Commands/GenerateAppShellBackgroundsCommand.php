<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Images\GenerateAppShellBackgroundVariants;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('app:generate-shell-backgrounds')]
#[Description('Generate responsive WebP variants for app shell backgrounds from the PNG originals')]
class GenerateAppShellBackgroundsCommand extends Command
{
    public function handle(GenerateAppShellBackgroundVariants $generateVariants): int
    {
        $manifest = $generateVariants();

        foreach ($manifest as $theme => $themeManifest) {
            if (! is_array($themeManifest)) {
                continue;
            }

            $variantCount = 0;

            foreach (($themeManifest['variants'] ?? []) as $formatVariants) {
                if (is_array($formatVariants)) {
                    $variantCount += count($formatVariants);
                }
            }

            $this->info(sprintf(
                'Generated %d %s background WebP variant(s).',
                $variantCount,
                $theme,
            ));
        }

        $outputDir = (string) config('media.app_shell_background.output_dir', 'images/app/backgrounds');
        $this->line('Manifest: public/'.$outputDir.'/manifest.json');

        return self::SUCCESS;
    }
}
