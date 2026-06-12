<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PruneSessionsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_deletes_stale_session_rows(): void
    {
        Config::set('session.driver', 'database');
        Config::set('session.lifetime', 120);
        Config::set('housekeeping.sessions_grace_days', 7);

        $staleActivity = now()
            ->subMinutes(120)
            ->subDays(8)
            ->getTimestamp();

        $freshActivity = now()->getTimestamp();

        DB::table('sessions')->insert([
            [
                'id' => 'stale-session',
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => 'payload',
                'last_activity' => $staleActivity,
            ],
            [
                'id' => 'fresh-session',
                'user_id' => null,
                'ip_address' => '127.0.0.1',
                'user_agent' => 'test',
                'payload' => 'payload',
                'last_activity' => $freshActivity,
            ],
        ]);

        $this->artisan('housekeeping:prune-sessions')
            ->assertSuccessful();

        $this->assertDatabaseMissing('sessions', ['id' => 'stale-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'fresh-session']);
    }

    #[Test]
    public function dry_run_does_not_delete_stale_session_rows(): void
    {
        Config::set('session.driver', 'database');
        Config::set('session.lifetime', 120);
        Config::set('housekeeping.sessions_grace_days', 7);

        DB::table('sessions')->insert([
            'id' => 'stale-session',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => now()->subDays(30)->getTimestamp(),
        ]);

        $this->artisan('housekeeping:prune-sessions', ['--dry-run' => true])
            ->assertSuccessful();

        $this->assertDatabaseHas('sessions', ['id' => 'stale-session']);
    }
}
