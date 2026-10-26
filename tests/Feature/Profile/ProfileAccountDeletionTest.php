<?php

namespace Tests\Feature\Profile;

use App\Actions\Profile\AnonymizeUser;
use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Livewire\Volt\Volt;
use Tests\TestCase;

class ProfileAccountDeletionTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_delete_their_account(): void
    {
        $user = User::factory()->create([
            'email' => 'real@example.com',
            'is_event_organizer' => true,
        ]);
        $user->profile()->update([
            'discord_handle' => 'real#1234',
            'current_location' => 'Warsaw',
            'show_contact_email' => true,
        ]);

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->call('promptDeleteUser')
            ->set('passwordConfirmationPassword', 'password')
            ->call('runPasswordConfirmation');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        $fresh = $user->fresh();

        $this->assertNotNull($fresh);
        $this->assertTrue($fresh->is_deleted);
        $this->assertNull($fresh->name);
        $this->assertSame('deleted+'.$user->id.'@deleted.invalid', $fresh->email);
        $this->assertFalse($fresh->is_event_organizer);

        $profile = $fresh->profile;
        $this->assertNull($profile->discord_handle);
        $this->assertNull($profile->current_location);
        $this->assertFalse($profile->show_contact_email);
    }

    public function test_deleted_user_is_presented_as_deleted_in_badge(): void
    {
        $user = User::factory()->create(['nickname' => 'realnick']);
        $user->profile()->update(['avatar_source' => AvatarSource::Generated]);

        app(AnonymizeUser::class)($user);

        $fresh = $user->fresh();

        $this->assertSame(__('ui.common.deleted_user'), $fresh->badgeDisplayName());
        $this->assertSame(__('ui.common.deleted_user'), $fresh->displayName());

        $html = Blade::render(
            '<x-user-badge :user="$user" />',
            ['user' => $fresh],
        );

        $this->assertStringContainsString(__('ui.common.deleted_user'), $html);
        $this->assertStringNotContainsString('realnick', $html);
    }

    public function test_delete_account_button_opens_password_confirmation_modal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Volt::test('profile.delete-user-form')
            ->call('promptDeleteUser')
            ->assertSet('confirmingPassword', true)
            ->assertSet('passwordConfirmationAction', 'deleteUser');
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $component = Volt::test('profile.delete-user-form')
            ->call('promptDeleteUser')
            ->set('passwordConfirmationPassword', 'wrong-password')
            ->call('runPasswordConfirmation');

        $component
            ->assertHasErrors('passwordConfirmationPassword')
            ->assertNoRedirect();

        $this->assertNotNull($user->fresh());
    }
}
