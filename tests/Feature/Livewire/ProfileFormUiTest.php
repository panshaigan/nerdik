<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Profile\ProfileTabs;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileFormUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_validation_shows_errors_and_reports_failed_tab(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-identity-information-form')
            ->set('nickname', '')
            ->call('updateIdentityInformation')
            ->assertHasErrors(['nickname'])
            ->assertSeeHtml('data-ui="form-errors"')
            ->assertDispatched('profile-tab-validation-failed', tab: 'identity');
    }

    public function test_profile_tabs_shows_error_badge_when_tab_validation_fails(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileTabs::class)
            ->dispatch('profile-tab-validation-failed', tab: 'identity')
            ->assertSet('tabsWithErrors.identity', true);
    }

    public function test_password_validation_reports_advanced_tab(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-password-form')
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('updatePassword')
            ->set('passwordConfirmationPassword', 'wrong-password')
            ->call('runPasswordConfirmation')
            ->assertHasErrors(['passwordConfirmationPassword'])
            ->assertDispatched('profile-tab-validation-failed', tab: 'advanced');
    }

    public function test_email_change_validation_reports_advanced_tab(): void
    {
        $user = User::factory()->create([
            'email' => 'current@example.com',
            'password' => bcrypt('correct-password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-email-form')
            ->set('new_email', 'new@example.com')
            ->call('requestEmailChange')
            ->set('passwordConfirmationPassword', 'wrong-password')
            ->call('runPasswordConfirmation')
            ->assertHasErrors(['passwordConfirmationPassword'])
            ->assertDispatched('profile-tab-validation-failed', tab: 'advanced');
    }

    public function test_organization_select_is_disabled_when_user_has_no_organizations(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-identity-information-form')
            ->assertSeeHtml('disabled')
            ->assertSee(__('ui.organizations.empty'));
    }

    public function test_organization_select_is_enabled_when_user_has_organizations(): void
    {
        $user = User::factory()->create();
        Organization::factory()->create([
            'created_by' => $user->id,
        ]);

        $this->actingAs($user);

        $html = Volt::test('profile.update-identity-information-form')
            ->html();

        $this->assertStringNotContainsString(__('ui.organizations.empty'), $html);
        $this->assertMatchesRegularExpression('/<select[^>]*wire:model="organization_id"[^>]*>/', $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*wire:model="organization_id"[^>]*disabled[^>]*>/', $html);
    }

    public function test_successful_identity_update_clears_tab_validation_state(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-identity-information-form')
            ->set('name', 'Test User')
            ->set('nickname', 'test-user')
            ->set('timezone', 'Europe/Warsaw')
            ->call('updateIdentityInformation')
            ->assertHasNoErrors()
            ->assertDispatched('profile-tab-validation-cleared', tab: 'identity');
    }
}
