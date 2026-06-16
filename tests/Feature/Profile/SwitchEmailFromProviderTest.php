<?php

namespace Tests\Feature\Profile;

use App\Models\User;
use App\Support\Profile\ProviderEmailOptions;
use Illuminate\Auth\Events\Verified;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use Tests\TestCase;

class SwitchEmailFromProviderTest extends TestCase
{
    use RefreshDatabase;

    public function test_opening_provider_email_modal_requires_selected_email(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);
        $user->profile()->update([
            'facebook_id' => 'fb-123',
            'facebook_email' => 'facebook@example.com',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('openProviderEmailSwitchModal')
            ->assertSet('confirmingProviderEmailSwitch', false)
            ->set('selected_provider_email', 'facebook@example.com')
            ->call('openProviderEmailSwitchModal')
            ->assertSet('confirmingProviderEmailSwitch', true);
    }

    public function test_selecting_different_account_email_opens_confirmation_modal(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);
        $user->profile()->update([
            'facebook_id' => 'fb-123',
            'facebook_email' => 'facebook@example.com',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->set('selected_email', 'facebook@example.com')
            ->assertSet('selected_provider_email', 'facebook@example.com')
            ->assertSet('confirmingProviderEmailSwitch', true);
    }

    public function test_provider_email_modal_renders_without_static_id(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);
        $user->profile()->update([
            'facebook_id' => 'fb-123',
            'facebook_email' => 'facebook@example.com',
        ]);

        $this->actingAs($user);

        $html = Volt::test('profile.update-contact-information-form')
            ->set('selected_provider_email', 'facebook@example.com')
            ->html();

        $this->assertStringContainsString('data-ui="profile-provider-email-modal"', $html);
        $this->assertStringNotContainsString('id="ui-profile-provider-email-modal"', $html);
        $this->assertStringContainsString('confirmingProviderEmailSwitch', $html);
    }

    public function test_user_can_switch_email_to_facebook_provider_email(): void
    {
        Event::fake([Verified::class]);

        $user = User::factory()->unverified()->create([
            'email' => 'local@example.com',
            'pending_email' => 'pending@example.com',
        ]);
        $user->profile()->update([
            'facebook_id' => 'fb-123',
            'facebook_email' => 'facebook@example.com',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->set('selected_provider_email', 'facebook@example.com')
            ->call('switchEmailFromProvider')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('facebook@example.com', $user->email);
        $this->assertNull($user->pending_email);
        $this->assertNotNull($user->email_verified_at);
        Event::assertDispatched(Verified::class);
    }

    public function test_provider_with_same_email_as_current_is_hidden_from_dropdown(): void
    {
        $user = User::factory()->create([
            'email' => 'same@example.com',
        ]);
        $user->profile()->update([
            'google_id' => 'google-1',
            'google_email' => 'same@example.com',
            'facebook_id' => 'fb-1',
            'facebook_email' => 'other@example.com',
        ]);

        $this->actingAs($user);

        $options = Volt::test('profile.update-contact-information-form')
            ->instance()
            ->providerEmailOptions();

        $this->assertCount(1, $options);
        $this->assertSame('other@example.com', $options[0]['id']);
        $this->assertSame('other@example.com', $options[0]['name']);
    }

    public function test_duplicate_emails_from_multiple_providers_are_shown_once(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);
        $user->profile()->update([
            'google_id' => 'google-1',
            'google_email' => 'shared@example.com',
            'facebook_id' => 'fb-1',
            'facebook_email' => 'shared@example.com',
            'discord_id' => 'discord-1',
            'discord_email' => 'unique@example.com',
        ]);

        $options = ProviderEmailOptions::for($user);

        $this->assertCount(2, $options);
        $this->assertSame(['shared@example.com', 'unique@example.com'], array_column($options, 'id'));
        $this->assertSame(array_column($options, 'id'), array_column($options, 'name'));
    }

    public function test_provider_without_stored_email_is_hidden_from_dropdown(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);
        $user->profile()->update([
            'facebook_id' => 'fb-no-email',
            'facebook_email' => null,
        ]);

        $this->actingAs($user);

        $options = Volt::test('profile.update-contact-information-form')
            ->instance()
            ->providerEmailOptions();

        $this->assertSame([], $options);
        $this->assertStringContainsString('data-ui="profile-contact-email-row"', Volt::test('profile.update-contact-information-form')->html());
    }

    public function test_google_unverified_email_is_not_stored_or_offered(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);
        $user->profile()->update([
            'google_id' => 'google-unverified',
            'google_email' => null,
            'facebook_id' => 'fb-1',
            'facebook_email' => 'facebook@example.com',
        ]);

        $this->actingAs($user);

        $options = Volt::test('profile.update-contact-information-form')
            ->instance()
            ->providerEmailOptions();

        $this->assertCount(1, $options);
        $this->assertSame('facebook@example.com', $options[0]['id']);
    }

    public function test_switch_rejects_email_already_used_by_another_account(): void
    {
        User::factory()->create(['email' => 'taken@example.com']);

        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);
        $user->profile()->update([
            'discord_id' => 'discord-1',
            'discord_email' => 'taken@example.com',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->set('selected_provider_email', 'taken@example.com')
            ->call('switchEmailFromProvider')
            ->assertHasErrors(['selected_provider_email'])
            ->assertDispatched('profile-tab-validation-failed', tab: 'contact');

        $this->assertSame('local@example.com', $user->fresh()->email);
    }

    public function test_unlinking_provider_clears_stored_provider_email(): void
    {
        $user = User::factory()->create([
            'email' => 'local@example.com',
        ]);
        $user->profile()->update([
            'google_id' => 'google-1',
            'google_email' => 'google@example.com',
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-contact-information-form')
            ->call('unlinkGoogle')
            ->assertHasNoErrors();

        $profile = $user->fresh()->profile;
        $this->assertNull($profile?->google_id);
        $this->assertNull($profile?->google_email);
    }

    public function test_provider_email_switch_section_is_hidden_when_no_options_exist(): void
    {
        $user = User::factory()->create([
            'email' => 'only@example.com',
        ]);

        $this->actingAs($user);

        $html = Volt::test('profile.update-contact-information-form')->html();

        $this->assertStringContainsString('data-ui="profile-contact-email-row"', $html);
        $this->assertStringContainsString('disabled', $html);
    }
}
