<?php

namespace Tests\Feature\Broadcast;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Broadcasting\BroadcastManager;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActivityParticipationChannelTest extends TestCase
{
    use RefreshDatabase;

    #[\Override]
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'broadcasting.default' => 'reverb',
            'broadcasting.connections.reverb.key' => 'test-key',
            'broadcasting.connections.reverb.secret' => 'test-secret',
            'broadcasting.connections.reverb.app_id' => 'test-app',
            'broadcasting.connections.reverb.options.host' => 'localhost',
            'broadcasting.connections.reverb.options.port' => 8080,
            'broadcasting.connections.reverb.options.scheme' => 'http',
            'broadcasting.connections.reverb.options.useTLS' => false,
        ]);

        app(BroadcastManager::class)->forgetDrivers();

        require base_path('routes/channels.php');
    }

    public function test_any_logged_in_user_can_authorize_activity_channel(): void
    {
        $activity = Activity::factory()->create();
        $firstUser = User::factory()->create();
        $secondUser = User::factory()->create();

        $this->actingAs($firstUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-activity.'.$activity->id,
            ])
            ->assertSuccessful();

        $this->actingAs($secondUser)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '8765.4321',
                'channel_name' => 'private-activity.'.$activity->id,
            ])
            ->assertSuccessful();
    }

    public function test_activity_channel_denied_when_activity_does_not_exist(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->postJson('/broadcasting/auth', [
                'socket_id' => '1234.5678',
                'channel_name' => 'private-activity.999999',
            ])
            ->assertForbidden();
    }

    public function test_guest_cannot_authorize_activity_channel(): void
    {
        $activity = Activity::factory()->create();

        $this->postJson('/broadcasting/auth', [
            'socket_id' => '1234.5678',
            'channel_name' => 'private-activity.'.$activity->id,
        ])->assertForbidden();
    }
}
