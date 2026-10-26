<?php

namespace Tests\Feature\Profile;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileContactFormTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_unlink_google_opens_confirm_modal_before_unlinking(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['google_id' => 'google-to-unlink']);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('confirmUnlinkGoogle')
            ->assertSet('confirmModalOpen', true)
            ->assertSet('confirmModalTitle', __('ui.profile.integrations_google_unlink'))
            ->assertSet('confirmModalMessage', __('ui.profile.integrations_google_unlink_confirm'))
            ->call('runConfirmedAction')
            ->assertSet('confirmModalOpen', false)
            ->assertSet('google_id', '');

        $this->assertNull($user->fresh()->profile?->google_id);
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
        $this->assertStringContainsString(__('ui.profile.integrations_facebook_profile_url_label'), $html);
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
        $this->assertStringContainsString(__('ui.profile.integrations_facebook_profile_url_label'), $html);
    }

    public function test_contact_form_persists_facebook_profile_url_on_save(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->set('facebook_profile_url', 'https://www.facebook.com/janedoe')
            ->call('saveFacebookProfileUrl')
            ->assertHasNoErrors();

        $this->assertSame(
            'https://www.facebook.com/janedoe',
            $user->fresh()->profile?->facebook_profile_url,
        );
    }

    public function test_contact_form_rejects_non_facebook_profile_url_host(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->set('facebook_profile_url', 'https://example.com/profile')
            ->call('saveFacebookProfileUrl')
            ->assertHasErrors(['facebook_profile_url']);
    }

    public function test_contact_form_rejects_invalid_facebook_profile_url(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->set('facebook_profile_url', 'not-a-url')
            ->call('saveFacebookProfileUrl')
            ->assertHasErrors(['facebook_profile_url']);
    }

    public function test_unlink_facebook_clears_facebook_id(): void
    {
        $user = User::factory()->create();
        $user->profile()->update([
            'facebook_id' => 'fb-to-unlink',
            'facebook_avatar_url' => 'https://facebook.com/avatar.jpg',
            'facebook_profile_url' => 'https://www.facebook.com/janedoe',
            'facebook_data' => [
                'profile_url' => 'https://www.facebook.com/zuck',
            ],
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('unlinkFacebook')
            ->assertHasNoErrors()
            ->assertSet('facebook_id', '')
            ->assertSet('facebook_profile_url', '');

        $profile = $user->fresh()->profile;
        $this->assertNull($profile?->facebook_id);
        $this->assertNull($profile?->facebook_avatar_url);
        $this->assertNull($profile?->facebook_profile_url);
        $this->assertNull($profile?->facebook_data);
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

    public function test_unlink_facebook_opens_confirm_modal_before_unlinking(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['facebook_id' => 'fb-to-unlink']);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('confirmUnlinkFacebook')
            ->assertSet('confirmModalOpen', true)
            ->assertSet('confirmModalTitle', __('ui.profile.integrations_facebook_unlink'))
            ->assertSet('confirmModalMessage', __('ui.profile.integrations_facebook_unlink_confirm'))
            ->call('runConfirmedAction')
            ->assertSet('confirmModalOpen', false)
            ->assertSet('facebook_id', '');

        $this->assertNull($user->fresh()->profile?->facebook_id);
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

    public function test_unlink_discord_opens_confirm_modal_before_unlinking(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['discord_id' => 'dc-to-unlink']);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('confirmUnlinkDiscord')
            ->assertSet('confirmModalOpen', true)
            ->assertSet('confirmModalTitle', __('ui.profile.integrations_discord_unlink'))
            ->assertSet('confirmModalMessage', __('ui.profile.integrations_discord_unlink_confirm'))
            ->call('runConfirmedAction')
            ->assertSet('confirmModalOpen', false)
            ->assertSet('discord_id', '');

        $this->assertNull($user->fresh()->profile?->discord_id);
    }

    public function test_contact_visibility_preferences_can_be_updated(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->set('show_contact_email', true)
            ->assertHasNoErrors()
            ->tap(function ($component): void {
                $this->assertStringContainsString(
                    'window.toast',
                    $component->effects['xjs'][0]['expression'] ?? '',
                );
            });

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
}
