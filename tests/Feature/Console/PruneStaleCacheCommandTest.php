<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PruneStaleCacheCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_expired_cache_rows(): void
    {
        Config::set('cache.default', 'database');

        $expired = now()->subHour()->getTimestamp();
        $valid = now()->addHour()->getTimestamp();

        DB::table('cache')->insert([
            ['key' => 'expired-key', 'value' => 'value', 'expiration' => $expired],
            ['key' => 'valid-key', 'value' => 'value', 'expiration' => $valid],
        ]);

        DB::table('cache_locks')->insert([
            ['key' => 'expired-lock', 'owner' => 'owner', 'expiration' => $expired],
            ['key' => 'valid-lock', 'owner' => 'owner', 'expiration' => $valid],
        ]);

        $this->artisan('housekeeping:prune-cache')
            ->assertSuccessful();

        $this->assertDatabaseMissing('cache', ['key' => 'expired-key']);
        $this->assertDatabaseHas('cache', ['key' => 'valid-key']);
        $this->assertDatabaseMissing('cache_locks', ['key' => 'expired-lock']);
        $this->assertDatabaseHas('cache_locks', ['key' => 'valid-lock']);
    }

    #[Test]
    public function dry_run_does_not_delete_expired_cache_rows(): void
    {
        Config::set('cache.default', 'database');

        DB::table('cache')->insert([
            'key' => 'expired-key',
            'value' => 'value',
            'expiration' => now()->subHour()->getTimestamp(),
        ]);

        $this->artisan('housekeeping:prune-cache', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('cache', ['key' => 'expired-key']);
    }
}
