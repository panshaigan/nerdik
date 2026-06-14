<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Profile\ProfileTabs;
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
            ->assertSeeHtml('novalidate')
            ->assertSeeHtml('data-ui="form-errors"')
            ->assertDispatched('profile-tab-validation-failed', tab: 'identity');
    }

    public function test_profile_tabs_shows_error_badge_when_tab_validation_fails(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ProfileTabs::class)
            ->dispatch('profile-tab-validation-failed', tab: 'identity')
            ->assertSet('tabsWithErrors.identity', true)
            ->assertSeeHtml('badge-error');
    }

    public function test_password_validation_reports_advanced_tab(): void
    {
        $user = User::factory()->create([
            'password' => bcrypt('correct-password'),
        ]);

        $this->actingAs($user);

        Volt::test('profile.update-password-form')
            ->set('current_password', 'wrong-password')
            ->set('password', 'new-secure-password')
            ->set('password_confirmation', 'new-secure-password')
            ->call('updatePassword')
            ->assertHasErrors(['current_password'])
            ->assertDispatched('profile-tab-validation-failed', tab: 'advanced');
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
