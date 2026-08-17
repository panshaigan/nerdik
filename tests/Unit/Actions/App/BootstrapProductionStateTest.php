<?php

declare(strict_types=1);

namespace Tests\Unit\Actions\App;

use App\Actions\App\BootstrapProductionState;
use Database\Seeders\BaseDataSeeder;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Mockery\MockInterface;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BootstrapProductionStateTest extends TestCase
{
    #[Test]
    public function it_wipes_leftover_media_and_runs_bootstrap_commands(): void
    {
        Storage::fake('public');
        config([
            'media-library.disk_name' => 'public',
            'media.storage_path_prefix' => 'media',
        ]);

        Storage::disk('public')->put('media/99/old.jpg', 'leftover');
        Cache::put('welcome.hero_tag_image', 'cached');

        /** @var Command&MockInterface $command */
        $command = Mockery::mock(Command::class);
        $command->shouldReceive('call')
            ->once()
            ->with('migrate:fresh', [
                '--force' => true,
                '--seed' => true,
                '--seeder' => BaseDataSeeder::class,
            ])
            ->andReturn(0);
        $command->shouldReceive('call')->once()->with('tags:seed-images')->andReturn(0);
        $command->shouldReceive('call')->once()->with('tags:recalculate-popularity')->andReturn(0);
        $command->shouldReceive('call')->once()->with('storage:link', ['--force' => true])->andReturn(0);

        app(BootstrapProductionState::class)($command);

        Storage::disk('public')->assertMissing('media/99/old.jpg');
        $this->assertFalse(Cache::has('welcome.hero_tag_image'));
    }
}
