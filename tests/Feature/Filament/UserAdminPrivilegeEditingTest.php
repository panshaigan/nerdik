<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class UserAdminPrivilegeEditingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_update_is_admin_through_filament_user_edit_page(): void
    {
        $admin = User::factory()->admin()->create();
        $managedUser = User::factory()->create([
            'is_admin' => false,
            'is_event_organizer' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $managedUser->id])
            ->fillForm([
                'name' => $managedUser->name,
                'nickname' => $managedUser->nickname,
                'email' => $managedUser->email,
                'password' => 'new-password-for-filament-user-edit',
                'is_admin' => true,
                'is_event_organizer' => $managedUser->is_event_organizer,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($managedUser->fresh()->is_admin);
    }

    #[Test]
    public function generic_user_mass_assignment_cannot_change_is_admin(): void
    {
        $user = User::factory()->create([
            'is_admin' => false,
        ]);

        $user->update([
            'is_admin' => true,
        ]);

        $this->assertFalse($user->fresh()->is_admin);
    }
}
