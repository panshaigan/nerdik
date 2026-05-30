<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Database\Seeders\TagSeeder;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('tags:seed-images')]
#[Description('Attach tag and listing images from database/seeders/tag_images (requires tags and activity types to exist)')]
class SeedTagImagesCommand extends Command
{
    public function handle(): int
    {
        $seeder = new TagSeeder;
        $seeder->seedTagImagesFromLibrary();
        $seeder->seedListingImages();

        $this->info('Tag and listing images attached from seeder library.');

        return self::SUCCESS;
    }
}
