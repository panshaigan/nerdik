<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Actions\Seeders\RemoveSeedAttachedMedia;
use Database\Seeders\TagSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tags:seed-images {--replace : Remove library-attached media before re-attaching}')]
#[Description('Attach tag and listing images from database/seeders/tag_images, then generate conversions and responsive thumbnails (requires tags and activity types to exist)')]
class SeedTagImagesCommand extends Command
{
    public const REGENERATE_MISSING_COMMAND = 'media-library:regenerate --only-missing --with-responsive-images';

    public function handle(RemoveSeedAttachedMedia $removeSeedAttachedMedia): int
    {
        if ((bool) $this->option('replace')) {
            $removeSeedAttachedMedia->forTagLibrary();
            $removeSeedAttachedMedia->forActivityListingDefaults();
            $removeSeedAttachedMedia->forEventListingDefaults();
        }

        $seeder = new TagSeeder;
        $seeder->seedTagImagesFromLibrary();
        $seeder->seedListingImages();

        $this->info('Tag and listing images attached from seeder library.');

        $this->call('media-library:regenerate', [
            '--only-missing' => true,
            '--with-responsive-images' => true,
            '--no-interaction' => true,
        ]);

        $this->info('Conversions and responsive thumbnails generated for seeded media.');
        $this->line('To backfill older attachments that predate this pipeline, run:');
        $this->line('  php artisan '.self::REGENERATE_MISSING_COMMAND);

        return self::SUCCESS;
    }
}
