<?php

namespace Tests\Feature;

use App\Enums\AvatarSource;
use App\Livewire\Activities\UserBadgeContact;
use App\Livewire\Activities\UserContactPopover;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\ActivityUser;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile');

        $response
            ->assertOk()
            ->assertSeeVolt('profile.profile-tabs')
            ->assertSeeVolt('profile.update-identity-information-form')
            ->assertSeeVolt('profile.update-contact-information-form')
            ->assertSeeVolt('profile.update-avatar-form')
            ->assertSeeVolt('profile.update-password-form')
            ->assertSeeVolt('profile.update-email-form')
            ->assertSeeVolt('profile.notification-settings-form')
            ->assertSeeVolt('profile.delete-user-form');
    }

    public function test_profile_page_fires_toast_from_oauth_linking_session(): void
    {
        $user = User::factory()->create();
        $message = __('ui.profile.oauth_link_facebook_success');

        $response = $this
            ->actingAs($user)
            ->withSession([
                'ui.toast' => [
                    'type' => 'success',
                    'title' => $message,
                ],
            ])
            ->get('/profile?tab=avatar');

        $response
            ->assertOk()
            ->assertSee($message, false)
            ->assertSee('window.toast', false);
    }

    public function test_identity_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-identity-information-form')
            ->set('name', 'Test User')
            ->set('nickname', 'test-user')
            ->set('timezone', 'Europe/Warsaw')
            ->call('updateIdentityInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test-user', $user->nickname);
        $this->assertSame('Europe/Warsaw', $user->profile?->timezone);
    }

    public function test_contact_form_displays_user_email(): void
    {
        $user = User::factory()->create([
            'email' => 'contact@example.com',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->assertSet('email', 'contact@example.com');
    }

    public function test_contact_form_shows_google_link_when_not_connected(): void
    {
        config([
            'services.google.client_id' => 'stub-client.apps.googleusercontent.com',
            'services.google.client_secret' => 'stub-secret',
            'services.facebook.client_id' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user);

        $html = Volt::test('profile.update-contact-information-form')->html();

        $this->assertStringContainsString('/auth/google', $html);
        $this->assertStringContainsString('return_tab=contact', $html);
        $this->assertStringContainsString(__('ui.profile.avatar_link_google'), $html);
        $this->assertStringNotContainsString(__('ui.profile.integrations_google_unlink'), $html);
    }

    public function test_contact_form_shows_google_id_when_connected(): void
    {
        config(['services.facebook.client_id' => null]);

        $user = User::factory()->create();
        $user->profile()->update(['google_id' => 'google-connected-99']);

        $this->actingAs($user);

        $html = Volt::test('profile.update-contact-information-form')->html();

        $this->assertStringContainsString('google-connected-99', $html);
        $this->assertStringContainsString(__('ui.profile.integrations_google_unlink'), $html);
        $this->assertStringNotContainsString('/auth/google', $html);
    }

    public function test_unlink_google_clears_google_id(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'google_id' => 'google-to-unlink',
            'google_avatar_url' => 'https://google.com/avatar.jpg',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('unlinkGoogle')
            ->assertHasNoErrors()
            ->assertSet('google_id', '');

        $profile = $user->fresh()->profile;
        $this->assertNull($profile?->google_id);
        $this->assertNull($profile?->google_avatar_url);
    }

    public function test_unlink_google_resets_avatar_source_when_google(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $path = 'avatars/'.$user->id.'.webp';
        Storage::disk('public')->put($path, 'fake-webp-bytes');
        $user->profile()->update([
            'google_id' => 'google-avatar-user',
            'google_avatar_url' => 'https://google.com/avatar.jpg',
            'avatar_source' => AvatarSource::Google,
            'avatar_path' => $path,
            'avatar_cache_signature' => 'sig',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('unlinkGoogle')
            ->assertHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertNull($profile?->google_id);
        $this->assertSame(AvatarSource::Generated, $profile?->avatar_source);
        $this->assertNull($profile?->avatar_path);
        $this->assertNull($profile?->avatar_cache_signature);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_contact_form_shows_facebook_link_when_not_connected(): void
    {
        config([
            'services.facebook.client_id' => 'stub-fb-client-id',
            'services.facebook.client_secret' => 'stub-fb-secret',
            'services.google.client_id' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user);

        $html = Volt::test('profile.update-contact-information-form')->html();

        $this->assertStringContainsString('/auth/facebook', $html);
        $this->assertStringContainsString('return_tab=contact', $html);
        $this->assertStringContainsString(__('ui.profile.avatar_link_facebook'), $html);
        $this->assertStringNotContainsString(__('ui.profile.integrations_facebook_unlink'), $html);
    }

    public function test_contact_form_shows_facebook_id_when_connected(): void
    {
        config(['services.google.client_id' => null]);

        $user = User::factory()->create();
        $user->profile()->update(['facebook_id' => 'fb-connected-99']);

        $this->actingAs($user);

        $html = Volt::test('profile.update-contact-information-form')->html();

        $this->assertStringContainsString('fb-connected-99', $html);
        $this->assertStringContainsString(__('ui.profile.integrations_facebook_unlink'), $html);
        $this->assertStringNotContainsString('/auth/facebook', $html);
    }

    public function test_unlink_facebook_clears_facebook_id(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'facebook_id' => 'fb-to-unlink',
            'facebook_avatar_url' => 'https://facebook.com/avatar.jpg',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('unlinkFacebook')
            ->assertHasNoErrors()
            ->assertSet('facebook_id', '');

        $profile = $user->fresh()->profile;
        $this->assertNull($profile?->facebook_id);
        $this->assertNull($profile?->facebook_avatar_url);
    }

    public function test_unlink_facebook_resets_avatar_source_when_facebook(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $path = 'avatars/'.$user->id.'.webp';
        Storage::disk('public')->put($path, 'fake-webp-bytes');
        $user->profile()->update([
            'facebook_id' => 'fb-avatar-user',
            'facebook_avatar_url' => 'https://facebook.com/avatar.jpg',
            'avatar_source' => AvatarSource::Facebook,
            'avatar_path' => $path,
            'avatar_cache_signature' => 'sig',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('unlinkFacebook')
            ->assertHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertNull($profile?->facebook_id);
        $this->assertSame(AvatarSource::Generated, $profile?->avatar_source);
        $this->assertNull($profile?->avatar_path);
        $this->assertNull($profile?->avatar_cache_signature);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_contact_form_shows_discord_link_when_not_connected(): void
    {
        config([
            'services.discord.client_id' => 'stub-dc-client-id',
            'services.discord.client_secret' => 'stub-dc-secret',
            'services.google.client_id' => null,
            'services.facebook.client_id' => null,
        ]);

        $user = User::factory()->create();

        $this->actingAs($user);

        $html = Volt::test('profile.update-contact-information-form')->html();

        $this->assertStringContainsString('/auth/discord', $html);
        $this->assertStringContainsString('return_tab=contact', $html);
        $this->assertStringContainsString(__('ui.profile.avatar_link_discord'), $html);
        $this->assertStringNotContainsString(__('ui.profile.integrations_discord_unlink'), $html);
    }

    public function test_contact_form_shows_discord_id_when_connected(): void
    {
        config(['services.google.client_id' => null, 'services.facebook.client_id' => null]);

        $user = User::factory()->create();
        $user->profile()->update(['discord_id' => 'dc-connected-99']);

        $this->actingAs($user);

        $html = Volt::test('profile.update-contact-information-form')->html();

        $this->assertStringContainsString('dc-connected-99', $html);
        $this->assertStringContainsString(__('ui.profile.integrations_discord_unlink'), $html);
        $this->assertStringNotContainsString('/auth/discord', $html);
    }

    public function test_unlink_discord_clears_discord_id(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'discord_id' => 'dc-to-unlink',
            'discord_avatar_url' => 'https://cdn.discordapp.com/avatars/1/abc.webp',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('unlinkDiscord')
            ->assertHasNoErrors()
            ->assertSet('discord_id', '');

        $profile = $user->fresh()->profile;
        $this->assertNull($profile?->discord_id);
        $this->assertNull($profile?->discord_avatar_url);
    }

    public function test_unlink_discord_resets_avatar_source_when_discord(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $path = 'avatars/'.$user->id.'.webp';
        Storage::disk('public')->put($path, 'fake-webp-bytes');
        $user->profile()->update([
            'discord_id' => 'dc-avatar-user',
            'discord_avatar_url' => 'https://cdn.discordapp.com/avatars/1/abc.webp',
            'avatar_source' => AvatarSource::Discord,
            'avatar_path' => $path,
            'avatar_cache_signature' => 'sig',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('unlinkDiscord')
            ->assertHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertNull($profile?->discord_id);
        $this->assertSame(AvatarSource::Generated, $profile?->avatar_source);
        $this->assertNull($profile?->avatar_path);
        $this->assertNull($profile?->avatar_cache_signature);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_generated_avatar_colors_can_be_saved(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-avatar-form')
            ->set('avatar_source', 'generated')
            ->set('avatar_bg_color', '#112233')
            ->set('avatar_text_color', '#ddeeff')
            ->set('avatar_initials', 'xyz')
            ->call('updateAvatar');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('#112233', $user->profile?->avatar_bg_color);
        $this->assertSame('#ddeeff', $user->profile?->avatar_text_color);
        $this->assertSame('XYZ', $user->profile?->avatar_initials);
        $this->assertStringContainsString('name=XYZ', $user->avatarUrl());
        $this->assertStringContainsString('length=3', $user->avatarUrl());
    }

    public function test_user_can_attach_existing_organization_from_profile(): void
    {
        $user = User::factory()->create([
            'organization_id' => null,
        ]);
        $organization = Organization::factory()->create([
            'name' => 'Nerdik Org',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.update-identity-information-form')
            ->set('organization_id', $organization->id)
            ->call('updateIdentityInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertSame($organization->id, $user->refresh()->organization_id);
        $this->assertSame(1, Organization::query()->where('name', 'Nerdik Org')->count());
    }

    public function test_user_can_select_organization_they_created_from_profile(): void
    {
        $user = User::factory()->create([
            'organization_id' => null,
        ]);
        $organization = Organization::factory()->create([
            'name' => 'Brand New Org',
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.update-identity-information-form')
            ->set('organization_id', $organization->id)
            ->call('updateIdentityInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertSame($organization->id, $user->refresh()->organization_id);
    }

    public function test_user_can_clear_organization_from_profile(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.update-identity-information-form')
            ->set('organization_id', null)
            ->call('updateIdentityInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertNull($user->refresh()->organization_id);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'password')
            ->call('deleteUser');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();
        $this->assertNull($user->fresh());
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->set('password', 'wrong-password')
            ->call('deleteUser');

        $component
            ->assertHasErrors('password')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }

    public function test_user_badge_uses_ui_avatars_with_profile_colors(): void
    {
        $user = User::factory()->create([
            'nickname' => 'Color User',
        ]);
        $user->profile()->update([
            'avatar_bg_color' => '#112233',
            'avatar_text_color' => '#ddeeff',
            'avatar_initials' => 'CU',
        ]);

        $html = Blade::render('<x-user-badge :user="$user" avatar-only />', [
            'user' => $user->fresh('profile'),
        ]);

        $this->assertStringContainsString('ui-avatars.com/api/', $html);
        $this->assertStringContainsString('name=CU', $html);
        $this->assertStringContainsString('length=2', $html);
        $this->assertStringNotContainsString('name=Color%20User', $html);
        $this->assertStringContainsString('background=112233', $html);
        $this->assertStringContainsString('color=ddeeff', $html);
    }

    public function test_user_badge_uses_storage_url_for_uploaded_source(): void
    {
        Storage::fake('public');
        $user = User::factory()->create([
            'nickname' => 'Disk User',
        ]);
        $path = 'avatars/'.$user->id.'.webp';
        Storage::disk('public')->put($path, 'fake-webp-bytes');
        $user->profile()->update([
            'avatar_source' => 'uploaded',
            'avatar_path' => $path,
        ]);

        $html = Blade::render('<x-user-badge :user="$user" avatar-only />', [
            'user' => $user->fresh('profile'),
        ]);

        $this->assertStringContainsString('/storage/'.$path, $html);
    }

    public function test_user_badge_prefers_organization_when_provided(): void
    {
        $organization = Organization::factory()->create([
            'name' => 'Org Display Name',
            'acronym' => null,
        ]);
        $user = User::factory()->create([
            'nickname' => 'User Display Name',
        ]);

        $html = Blade::render('<x-user-badge :user="$user" :organization="$organization" avatar-only />', [
            'user' => $user,
            'organization' => $organization,
        ]);

        $this->assertStringContainsString('ui-avatars.com/api/', $html);
        $this->assertStringContainsString('name=Org%20Display%20Name', $html);
        $this->assertStringNotContainsString('name=User%20Display%20Name', $html);
        $this->assertStringContainsString('alt="Org Display Name"', $html);
    }

    public function test_user_badge_shows_organization_acronym_after_nickname(): void
    {
        $organization = Organization::factory()->create([
            'acronym' => 'CGN',
        ]);
        $user = User::factory()->create([
            'nickname' => 'pixel_mage',
            'organization_id' => $organization->id,
        ]);

        $html = Blade::render('<x-user-badge :user="$user" />', [
            'user' => $user->fresh('organization'),
        ]);

        $this->assertStringContainsString('pixel_mage [CGN]', $html);
    }

    public function test_user_badge_shows_nickname_only_when_organization_has_no_acronym(): void
    {
        $organization = Organization::factory()->create([
            'acronym' => null,
        ]);
        $user = User::factory()->create([
            'nickname' => 'solo_player',
            'organization_id' => $organization->id,
        ]);

        $html = Blade::render('<x-user-badge :user="$user" />', [
            'user' => $user->fresh('organization'),
        ]);

        $this->assertStringContainsString('solo_player', $html);
        $this->assertStringNotContainsString('[', $html);
    }

    public function test_user_badge_shows_nickname_only_when_user_has_no_organization(): void
    {
        $user = User::factory()->create([
            'nickname' => 'free_agent',
            'organization_id' => null,
        ]);

        $html = Blade::render('<x-user-badge :user="$user" />', [
            'user' => $user,
        ]);

        $this->assertStringContainsString('free_agent', $html);
        $this->assertStringNotContainsString('[', $html);
    }

    public function test_profile_page_has_tab_query_parameter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile?tab=contact');

        $response->assertOk();
        $response->assertSee('data-ui="profile-tabs"', false);
    }

    public function test_contact_visibility_preferences_can_be_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->set('show_contact_email', true)
            ->assertHasNoErrors();

        $user->profile()->update([
            'facebook_id' => 'fb-linked',
            'discord_id' => 'dc-linked',
            'google_id' => 'g-linked',
        ]);

        Volt::test('profile.update-contact-information-form')
            ->set('show_contact_facebook', false)
            ->set('show_contact_google', false)
            ->set('show_contact_discord', false)
            ->assertHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertTrue((bool) $profile?->show_contact_email);
        $this->assertFalse((bool) $profile?->show_contact_facebook);
        $this->assertFalse((bool) $profile?->show_contact_google);
        $this->assertFalse((bool) $profile?->show_contact_discord);
    }

    public function test_user_badge_contact_renders_modal_trigger(): void
    {
        $user = User::factory()->create();

        $html = Blade::render(
            '<x-user-badge :user="$user" :contact-popover="true" :contact-context-activity-id="1" />',
            ['user' => $user],
        );

        $this->assertStringContainsString('data-ui="user-badge-contact"', $html);
        $this->assertStringContainsString('data-ui="user-badge-contact-trigger"', $html);
        $this->assertStringContainsString('wire:click.stop="openModal"', $html);
        $this->assertStringContainsString('cursor-pointer', $html);
    }

    public function test_user_badge_contact_modal_opens_and_loads_popover_content(): void
    {
        $host = User::factory()->create(['email' => 'host@example.test']);
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'show_contact_email' => true,
        ]);

        Livewire::actingAs($host)
            ->test(UserBadgeContact::class, [
                'user' => $participant,
                'activityId' => $activity->id,
            ])
            ->assertSet('modalOpen', false)
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->assertSee('mailto:participant@example.test');
    }

    public function test_user_badge_contact_modal_opens_with_container_class_from_attributes(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $activity = Activity::factory()->create(['created_by' => $host->id]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'show_contact_email' => true,
        ]);

        Livewire::actingAs($host)
            ->test(UserBadgeContact::class, [
                'user' => $participant,
                'activityId' => $activity->id,
                'containerClass' => 'inline-flex min-w-0 min-w-0 flex-1',
            ])
            ->call('openModal')
            ->assertSet('modalOpen', true)
            ->assertSee('mailto:participant@example.test');
    }

    public function test_user_contact_popover_shows_contact_methods_for_host_and_participant(): void
    {
        $host = User::factory()->create(['email' => 'host@example.test']);
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'facebook_id' => '12345',
            'discord_id' => '67890',
            'google_email' => 'participant-google@example.test',
            'show_contact_email' => true,
            'show_contact_facebook' => true,
            'show_contact_google' => true,
            'show_contact_discord' => true,
        ]);

        $html = Livewire::actingAs($host)
            ->test(UserContactPopover::class, [
                'activityId' => $activity->id,
                'targetUserId' => $participant->id,
            ])
            ->html();

        $this->assertStringContainsString('mailto:participant@example.test', $html);
        $this->assertStringContainsString('https://m.me/12345', $html);
        $this->assertStringContainsString('https://discord.com/users/67890', $html);
        $this->assertStringContainsString('participant-google@example.test', $html);
    }

    public function test_user_contact_popover_hides_contact_methods_for_unauthorized_viewer(): void
    {
        $host = User::factory()->create();
        $participant = User::factory()->create(['email' => 'participant@example.test']);
        $stranger = User::factory()->create();
        $type = ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $activity = Activity::factory()->create([
            'created_by' => $host->id,
            'activity_type_id' => $type->id,
        ]);
        ActivityUser::query()->create([
            'activity_id' => $activity->id,
            'user_id' => $participant->id,
            'is_absent' => false,
            'deleted_at' => null,
        ]);
        $participant->profile()->update([
            'show_contact_email' => true,
            'show_contact_facebook' => true,
            'show_contact_google' => true,
            'show_contact_discord' => true,
        ]);

        $html = Livewire::actingAs($stranger)
            ->test(UserContactPopover::class, [
                'activityId' => $activity->id,
                'targetUserId' => $participant->id,
            ])
            ->html();

        $this->assertStringContainsString(__('ui.profile.contact_not_allowed'), $html);
        $this->assertStringNotContainsString('mailto:participant@example.test', $html);
    }
}
