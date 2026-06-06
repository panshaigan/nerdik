<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\ActivityLogoSource;
use App\Enums\EventLogoSource;
use App\Models\Activity;
use App\Models\Event;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('media:migrate-uploaded-logos {--dry-run : Report changes without writing}')]
#[Description('Attach legacy logo_path uploads to the Spatie logo media collection')]
final class MigrateUploadedLogosCommand extends Command
{
    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $migrated = 0;
        $skipped = 0;
        $failed = 0;

        Activity::query()
            ->where('logo_source', ActivityLogoSource::Upload)
            ->whereNotNull('logo_path')
            ->where('logo_path', '!=', '')
            ->orderBy('id')
            ->each(function (Activity $activity) use ($dryRun, &$migrated, &$skipped, &$failed): void {
                $result = $this->migrateModelLogo($activity, (string) $activity->logo_path, $dryRun);

                if ($result === 'migrated') {
                    $migrated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $failed++;
                }
            });

        Event::query()
            ->where('logo_source', EventLogoSource::Upload)
            ->whereNotNull('logo_path')
            ->where('logo_path', '!=', '')
            ->orderBy('id')
            ->each(function (Event $event) use ($dryRun, &$migrated, &$skipped, &$failed): void {
                $result = $this->migrateModelLogo($event, (string) $event->logo_path, $dryRun);

                if ($result === 'migrated') {
                    $migrated++;
                } elseif ($result === 'skipped') {
                    $skipped++;
                } else {
                    $failed++;
                }
            });

        $this->info("Migrated: {$migrated}, skipped: {$skipped}, failed: {$failed}".($dryRun ? ' (dry run)' : ''));

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return 'migrated'|'skipped'|'failed'
     */
    private function migrateModelLogo(Activity|Event $model, string $path, bool $dryRun): string
    {
        if ($model->getFirstMedia('logo') !== null) {
            $this->line("Skipping {$model->getMorphClass()} #{$model->id}: logo media already present.");

            return 'skipped';
        }

        if (! Storage::disk('public')->exists($path)) {
            $this->warn("Failed {$model->getMorphClass()} #{$model->id}: file missing at [{$path}].");

            return 'failed';
        }

        if ($dryRun) {
            $this->line("Would migrate {$model->getMorphClass()} #{$model->id} from [{$path}].");

            return 'migrated';
        }

        $absolutePath = Storage::disk('public')->path($path);
        $imageSize = @getimagesize($absolutePath);

        $model->addMedia($absolutePath)
            ->withCustomProperties([
                'width' => $imageSize !== false ? $imageSize[0] : 1280,
                'height' => $imageSize !== false ? $imageSize[1] : 720,
                'migrated_from' => $path,
            ])
            ->toMediaCollection('logo');

        Storage::disk('public')->delete($path);

        $model->forceFill(['logo_path' => null])->save();

        $this->line("Migrated {$model->getMorphClass()} #{$model->id} from [{$path}].");

        return 'migrated';
    }
}
