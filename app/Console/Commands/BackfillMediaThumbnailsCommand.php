<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

#[Signature('media:backfill-thumbnails')]
#[Description('Report missing media conversions/responsive images, then regenerate only missing derivatives (safe for production)')]
final class BackfillMediaThumbnailsCommand extends Command
{
    public const REGENERATE_COMMAND = 'media-library:regenerate --only-missing --with-responsive-images --force';

    /**
     * @var array<string, bool>
     */
    public const REGENERATE_OPTIONS = [
        '--only-missing' => true,
        '--with-responsive-images' => true,
        '--force' => true,
        '--no-interaction' => true,
    ];

    public function handle(): int
    {
        $this->info('Media thumbnail status (before):');
        $this->printStats($this->collectStats());

        $this->newLine();
        $this->info('Running: php artisan '.self::REGENERATE_COMMAND);

        $this->call('media-library:regenerate', self::REGENERATE_OPTIONS);

        $this->newLine();
        $this->info('Media thumbnail status (after):');
        $this->printStats($this->collectStats());

        $this->newLine();
        $this->line('Prod (compose-exec):');
        $this->line('  ./scripts/compose-exec.sh prod exec app php artisan media:backfill-thumbnails');
        $this->line('Or directly:');
        $this->line('  ./scripts/compose-exec.sh prod exec app php artisan '.self::REGENERATE_COMMAND.' --no-interaction');

        return self::SUCCESS;
    }

    /**
     * @return array{
     *     total: int,
     *     empty_conversions: int,
     *     empty_responsive: int,
     *     groups: list<array{model_type: string, collection_name: string, total: int, empty_conversions: int, empty_responsive: int}>
     * }
     */
    private function collectStats(): array
    {
        $total = 0;
        $emptyConversions = 0;
        $emptyResponsive = 0;
        /** @var array<string, array{model_type: string, collection_name: string, total: int, empty_conversions: int, empty_responsive: int}> $groups */
        $groups = [];

        Media::query()
            ->orderBy('id')
            ->each(function (Media $media) use (&$total, &$emptyConversions, &$emptyResponsive, &$groups): void {
                $total++;
                $lacksConversions = $this->lacksConversions($media);
                $lacksResponsive = $this->lacksResponsiveImages($media);

                if ($lacksConversions) {
                    $emptyConversions++;
                }

                if ($lacksResponsive) {
                    $emptyResponsive++;
                }

                $key = $media->model_type.'|'.$media->collection_name;

                if (! isset($groups[$key])) {
                    $groups[$key] = [
                        'model_type' => (string) $media->model_type,
                        'collection_name' => (string) $media->collection_name,
                        'total' => 0,
                        'empty_conversions' => 0,
                        'empty_responsive' => 0,
                    ];
                }

                $groups[$key]['total']++;

                if ($lacksConversions) {
                    $groups[$key]['empty_conversions']++;
                }

                if ($lacksResponsive) {
                    $groups[$key]['empty_responsive']++;
                }
            });

        usort(
            $groups,
            fn (array $a, array $b): int => [$b['empty_conversions'], $b['empty_responsive'], $a['model_type'], $a['collection_name']]
                <=> [$a['empty_conversions'], $a['empty_responsive'], $b['model_type'], $b['collection_name']],
        );

        return [
            'total' => $total,
            'empty_conversions' => $emptyConversions,
            'empty_responsive' => $emptyResponsive,
            'groups' => array_values($groups),
        ];
    }

    /**
     * @param  array{
     *     total: int,
     *     empty_conversions: int,
     *     empty_responsive: int,
     *     groups: list<array{model_type: string, collection_name: string, total: int, empty_conversions: int, empty_responsive: int}>
     * }  $stats
     */
    private function printStats(array $stats): void
    {
        $this->line(sprintf(
            'total=%d empty_conversions=%d empty_responsive=%d',
            $stats['total'],
            $stats['empty_conversions'],
            $stats['empty_responsive'],
        ));

        if ($stats['groups'] === []) {
            return;
        }

        $this->table(
            ['model_type', 'collection', 'total', 'empty_conversions', 'empty_responsive'],
            array_map(
                fn (array $group): array => [
                    class_basename($group['model_type']),
                    $group['collection_name'],
                    $group['total'],
                    $group['empty_conversions'],
                    $group['empty_responsive'],
                ],
                $stats['groups'],
            ),
        );
    }

    private function lacksConversions(Media $media): bool
    {
        $conversions = $media->generated_conversions;

        if (! is_array($conversions) || $conversions === []) {
            return true;
        }

        foreach ($conversions as $generated) {
            if ($generated) {
                return false;
            }
        }

        return true;
    }

    private function lacksResponsiveImages(Media $media): bool
    {
        $responsive = $media->responsive_images;

        return ! is_array($responsive) || $responsive === [];
    }
}
