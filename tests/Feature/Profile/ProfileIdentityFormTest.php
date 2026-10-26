<?php

namespace Tests\Feature\Profile;

use App\Enums\TimeDisplayFormat;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileIdentityFormTest extends TestCase
{
    use RefreshDatabase;

    public function test_identity_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-identity-information-form')
            ->set('name', 'Test User')
            ->set('nickname', 'test-user')
            ->set('timezone', 'Europe/Warsaw')
            ->set('time_display_format', '12h')
            ->call('updateIdentityInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test-user', $user->nickname);
        $this->assertSame('Europe/Warsaw', $user->profile?->timezone);
        $this->assertSame(TimeDisplayFormat::TwelveHour, $user->profile?->time_display_format);
    }

    public function test_identity_nickname_must_be_unique(): void
    {
        User::factory()->create(['nickname' => 'Klaudia']);
        $user = User::factory()->create(['nickname' => 'my-nick']);

        $this->actingAs($user);

        // Trailing space mirrors the production failure; trim runs before unique.
        Volt::test('profile.update-identity-information-form')
            ->set('name', 'Test User')
            ->set('nickname', 'Klaudia ')
            ->set('timezone', 'Europe/Warsaw')
            ->set('time_display_format', '24h')
            ->call('updateIdentityInformation')
            ->assertHasErrors(['nickname']);

        $this->assertSame('my-nick', $user->refresh()->nickname);
    }

    public function test_identity_nickname_is_trimmed_before_save(): void
    {
        $user = User::factory()->create(['nickname' => 'my-nick']);

        $this->actingAs($user);

        Volt::test('profile.update-identity-information-form')
            ->set('name', '  Test User  ')
            ->set('nickname', '  Klaudia  ')
            ->set('timezone', 'Europe/Warsaw')
            ->set('time_display_format', '24h')
            ->call('updateIdentityInformation')
            ->assertHasNoErrors();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('Klaudia', $user->nickname);
    }

    public function test_identity_allows_keeping_own_nickname(): void
    {
        $user = User::factory()->create(['nickname' => 'my-nick']);

        $this->actingAs($user);

        Volt::test('profile.update-identity-information-form')
            ->set('name', 'Test User')
            ->set('nickname', 'my-nick')
            ->set('timezone', 'Europe/Warsaw')
            ->set('time_display_format', '24h')
            ->call('updateIdentityInformation')
            ->assertHasNoErrors();

        $this->assertSame('my-nick', $user->refresh()->nickname);
    }

    public function test_identity_form_defaults_timezone_to_europe_warsaw_when_profile_timezone_is_null(): void
    {
        $user = User::factory()->create();
        $user->profile()->update(['timezone' => null]);

        $this->actingAs($user);

        Volt::test('profile.update-identity-information-form')
            ->assertSet('timezone', 'Europe/Warsaw');
    }

    public function test_identity_timezone_is_required(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.update-identity-information-form')
            ->set('name', 'Test User')
            ->set('nickname', 'test-user')
            ->set('timezone', '')
            ->set('time_display_format', '24h')
            ->call('updateIdentityInformation')
            ->assertHasErrors(['timezone']);
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
        $owner = User::factory()->create();
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $user = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.update-identity-information-form')
            ->assertSet('organization_id', $organization->id)
            ->set('organization_id', null)
            ->call('updateIdentityInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertNull($user->refresh()->organization_id);
    }

    public function test_assigned_organization_appears_in_profile_identity_options(): void
    {
        $owner = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'name' => 'Assigned Org',
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $member = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($member);

        Volt::test('profile.update-identity-information-form')
            ->assertSet('organization_id', $organization->id)
            ->assertSet('organizationOptions', fn (array $options): bool => collect($options)->contains(
                fn (array $option): bool => $option['id'] === $organization->id && $option['name'] === 'Assigned Org',
            ));
    }

    public function test_assigned_organization_enables_profile_identity_select(): void
    {
        $owner = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);
        $member = User::factory()->create([
            'organization_id' => $organization->id,
        ]);

        $this->actingAs($member);

        $html = Volt::test('profile.update-identity-information-form')->html();

        $this->assertStringNotContainsString(__('ui.organizations.empty'), $html);
        $this->assertDoesNotMatchRegularExpression('/<select[^>]*wire:model="organization_id"[^>]*disabled[^>]*>/', $html);
    }
}
