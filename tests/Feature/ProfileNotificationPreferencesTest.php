<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileNotificationPreferencesTest extends TestCase
{
    use RefreshDatabase;

    public function test_join_notification_defaults_to_milestones_only(): void
    {
        $user = User::factory()->create();

        $prefs = $user->resolvedNotificationPreferences()['activity_participant_joined'];

        $this->assertFalse($prefs['every_join']);
        $this->assertTrue($prefs['in_app']);
        $this->assertFalse($prefs['email']);
    }

    public function test_every_join_preference_can_be_saved(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $preferences = $user->resolvedNotificationPreferences();
        $preferences['activity_participant_joined']['every_join'] = true;
        $preferences['activity_participant_joined']['email'] = true;

        Volt::test('profile.notification-settings-form')
            ->set('preferences', $preferences)
            ->call('updateNotificationSettings')
            ->assertHasNoErrors();

        $user->refresh();
        $joinPrefs = $user->resolvedNotificationPreferences()['activity_participant_joined'];

        $this->assertTrue($joinPrefs['every_join']);
        $this->assertTrue($joinPrefs['email']);
    }

    public function test_notification_preferences_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $preferences = $user->resolvedNotificationPreferences();
        $preferences['waitlist_promoted']['email'] = false;

        Volt::test('profile.notification-settings-form')
            ->set('preferences', $preferences)
            ->call('updateNotificationSettings')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertFalse($user->resolvedNotificationPreferences()['waitlist_promoted']['email']);
        $this->assertTrue($user->resolvedNotificationPreferences()['waitlist_promoted']['in_app']);
    }

    public function test_scheduled_digest_items_respect_opt_out_on_either_channel(): void
    {
        $item = [
            'category' => 'interested_enrollment_window',
            'title' => 't',
            'lines' => [],
            'url' => '/x',
            'dedupe_key' => 'k',
        ];

        $userKeeps = User::factory()->create([
            'name' => 'Keeps',
        ]);
        $userKeeps->profile()->update([
            'notification_preferences' => [
                'scheduled_interested_enrollment_window' => [
                    'in_app' => false,
                    'email' => true,
                ],
            ],
        ]);

        $this->assertTrue($userKeeps->retainsScheduledDigestItem($item));

        $userDrops = User::factory()->create([
            'name' => 'Drops',
        ]);
        $userDrops->profile()->update([
            'notification_preferences' => [
                'scheduled_interested_enrollment_window' => [
                    'in_app' => false,
                    'email' => false,
                ],
            ],
        ]);

        $this->assertFalse($userDrops->retainsScheduledDigestItem($item));
    }
}
