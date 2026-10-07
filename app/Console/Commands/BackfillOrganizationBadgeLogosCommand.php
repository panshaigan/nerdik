<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Organizations\StoreUploadedOrganizationLogo;
use App\Enums\OrganizationLogoSource;
use App\Models\Organization;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;

#[Signature('organizations:backfill-badge-logos {--dry-run : Report changes without writing}')]
#[Description('Generate 72px badge WebP variants for uploaded organization logos')]
final class BackfillOrganizationBadgeLogosCommand extends Command
{
    private const int BADGE_SIZE = 72;

    public function handle(ImageManager $imageManager): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $created = 0;
        $skipped = 0;
        $failed = 0;

        Organization::query()
            ->where('logo_source', OrganizationLogoSource::Upload)
            ->whereNotNull('logo_path')
            ->where('logo_path', '!=', '')
            ->orderBy('id')
            ->each(function (Organization $organization) use ($dryRun, $imageManager, &$created, &$skipped, &$failed): void {
                $badgePath = StoreUploadedOrganizationLogo::badgeRelativePath($organization);

                if (Storage::disk('public')->exists($badgePath)) {
                    $skipped++;

                    return;
                }

                $logoPath = (string) $organization->logo_path;
                if ($logoPath === '' || ! Storage::disk('public')->exists($logoPath)) {
                    $this->warn("Failed organization #{$organization->id}: logo missing at [{$logoPath}].");
                    $failed++;

                    return;
                }

                if ($dryRun) {
                    $this->line("Would create [{$badgePath}] from [{$logoPath}].");
                    $created++;

                    return;
                }

                try {
                    $absoluteLogoPath = Storage::disk('public')->path($logoPath);
                    $encoded = $imageManager
                        ->read($absoluteLogoPath)
                        ->cover(self::BADGE_SIZE, self::BADGE_SIZE)
                        ->toWebp(85);

                    $written = Storage::disk('public')->put($badgePath, $encoded->toString(), [
                        'visibility' => 'public',
                    ]);

                    if ($written !== true) {
                        throw new \RuntimeException("Failed to write badge logo at [{$badgePath}].");
                    }

                    $created++;
                } catch (\Throwable $exception) {
                    $this->warn("Failed organization #{$organization->id}: {$exception->getMessage()}");
                    $failed++;
                }
            });

        $this->info("Created: {$created}, skipped: {$skipped}, failed: {$failed}".($dryRun ? ' (dry run)' : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }
}
