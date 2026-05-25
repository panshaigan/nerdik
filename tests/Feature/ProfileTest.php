<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Storage;
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
            ->assertSeeVolt('profile.notification-settings-form')
            ->assertSeeVolt('profile.delete-user-form');
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

    public function test_contact_information_can_be_updated(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.update-contact-information-form')
            ->set('email', $user->email)
            ->set('discord_handle', 'nerdik-user')
            ->call('updateContactInformation');

        $component
            ->assertHasNoErrors()
            ->assertNoRedirect();

        $this->assertSame('nerdik-user', $user->refresh()->profile?->discord_handle);
        $this->assertNotNull($user->email_verified_at);
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

    public function test_profile_page_has_tab_query_parameter(): void
    {
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get('/profile?tab=contact');

        $response->assertOk();
        $response->assertSee('data-ui="profile-tabs"', false);
    }
}
