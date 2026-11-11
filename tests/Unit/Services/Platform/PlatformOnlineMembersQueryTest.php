<?php

declare(strict_types=1);

namespace Tests\Unit\Services\Platform;

use App\Models\User;
use App\Services\Platform\PlatformOnlineMembersQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PlatformOnlineMembersQueryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(PlatformOnlineMembersQuery::CACHE_KEY);
    }

    #[Test]
    public function it_counts_distinct_members_with_recent_presence(): void
    {
        $now = Carbon::parse('2026-02-15 12:00:00');
        $alice = User::factory()->create();
        $bob = User::factory()->create();

        $this->touchPresence($alice->id, $now->copy()->subMinutes(1));
        $this->touchPresence($bob->id, $now->copy()->subMinutes(4));

        $this->assertSame(2, app(PlatformOnlineMembersQuery::class)->count($now));
    }

    #[Test]
    public function it_merges_recent_sessions_when_user_id_is_present(): void
    {
        $now = Carbon::parse('2026-02-15 12:00:00');
        $fromPresence = User::factory()->create();
        $fromSessionOnly = User::factory()->create();

        $this->touchPresence($fromPresence->id, $now->copy()->subMinute());
        $this->insertSession('session-only', $fromSessionOnly->id, $now->getTimestamp());

        $this->assertSame(2, app(PlatformOnlineMembersQuery::class)->count($now));
    }

    #[Test]
    public function it_ignores_stale_guest_and_deleted_presence(): void
    {
        $now = Carbon::parse('2026-02-15 12:00:00');
        $active = User::factory()->create();
        $deleted = User::factory()->create(['is_deleted' => true]);
        $idle = User::factory()->create();

        $this->touchPresence($active->id, $now->copy()->subMinutes(2));
        $this->touchPresence($deleted->id, $now);
        $this->touchPresence($idle->id, $now->copy()->subMinutes(6));
        $this->insertSession('guest', null, $now->getTimestamp());

        $this->assertSame(1, app(PlatformOnlineMembersQuery::class)->count($now));
    }

    #[Test]
    public function it_caches_the_count_until_the_key_is_forgotten(): void
    {
        $alice = User::factory()->create();
        $this->touchPresence($alice->id, now());

        $query = app(PlatformOnlineMembersQuery::class);

        $this->assertSame(1, $query->count());

        $this->touchPresence(User::factory()->create()->id, now());

        $this->assertSame(1, $query->count());

        Cache::forget(PlatformOnlineMembersQuery::CACHE_KEY);

        $this->assertSame(2, $query->count());
    }

    private function touchPresence(int $userId, Carbon $lastSeenAt): void
    {
        DB::table('user_presence')->upsert(
            [
                'user_id' => $userId,
                'last_seen_at' => $lastSeenAt,
            ],
            ['user_id'],
            ['last_seen_at'],
        );
    }

    private function insertSession(string $id, ?int $userId, int $lastActivity): void
    {
        DB::table('sessions')->insert([
            'id' => $id,
            'user_id' => $userId,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => 'payload',
            'last_activity' => $lastActivity,
        ]);
    }
}
