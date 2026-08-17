<?php

declare(strict_types=1);

namespace App\Actions\App;

use Database\Seeders\BaseDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;

class BootstrapProductionState
{
    public function __invoke(Command $command): void
    {
        $command->call('migrate:fresh', [
            '--force' => true,
            '--seed' => true,
            '--seeder' => BaseDataSeeder::class,
        ]);

        $this->clearLeftoverMediaFiles();

        $command->call('tags:seed-images');
        $command->call('tags:recalculate-popularity');

        Cache::forget('welcome.hero_tag_image');

        $command->call('storage:link', [
            '--force' => true,
        ]);
    }

    private function clearLeftoverMediaFiles(): void
    {
        $diskName = (string) config('media-library.disk_name', 'public');
        $prefix = (string) config('media.storage_path_prefix', 'media');

        Storage::disk($diskName)->deleteDirectory($prefix);
    }
}
