<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PruneLivewireUploadsCommandTest extends TestCase
{
    #[Test]
    public function it_deletes_stale_livewire_temporary_uploads(): void
    {
        Config::set('housekeeping.livewire_tmp_hours', 24);

        Storage::fake('tmp-for-tests');

        Storage::disk('tmp-for-tests')->put('livewire-tmp/fresh.txt', 'fresh');
        Storage::disk('tmp-for-tests')->put('livewire-tmp/stale.txt', 'stale');

        touch(
            Storage::disk('tmp-for-tests')->path('livewire-tmp/stale.txt'),
            now()->subDays(2)->getTimestamp(),
        );

        $this->artisan('housekeeping:prune-livewire-uploads')
            ->assertSuccessful();

        Storage::disk('tmp-for-tests')->assertExists('livewire-tmp/fresh.txt');
        Storage::disk('tmp-for-tests')->assertMissing('livewire-tmp/stale.txt');
    }

    #[Test]
    public function dry_run_does_not_delete_stale_livewire_temporary_uploads(): void
    {
        Config::set('housekeeping.livewire_tmp_hours', 24);

        Storage::fake('tmp-for-tests');
        Storage::disk('tmp-for-tests')->put('livewire-tmp/stale.txt', 'stale');

        touch(
            Storage::disk('tmp-for-tests')->path('livewire-tmp/stale.txt'),
            now()->subDays(2)->getTimestamp(),
        );

        $this->artisan('housekeeping:prune-livewire-uploads', ['--dry-run' => true])
            ->assertSuccessful();

        Storage::disk('tmp-for-tests')->assertExists('livewire-tmp/stale.txt');
    }
}
