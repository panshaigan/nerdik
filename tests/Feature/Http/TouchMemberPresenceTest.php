<?php

declare(strict_types=1);

namespace Tests\Feature\Http;

use App\Models\User;
use App\Services\Platform\PlatformOnlineMembersQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class TouchMemberPresenceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::forget(PlatformOnlineMembersQuery::CACHE_KEY);
    }

    #[Test]
    public function authenticated_web_request_records_member_presence_on_terminate(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertSuccessful();

        $this->assertDatabaseHas('user_presence', [
            'user_id' => $user->id,
        ]);

        $this->assertSame(1, app(PlatformOnlineMembersQuery::class)->count());
    }

    #[Test]
    public function guest_requests_do_not_record_presence(): void
    {
        $this->get('/')
            ->assertSuccessful();

        $this->assertSame(0, DB::table('user_presence')->count());
    }
}
