<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Users\Pages\EditUser;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\SentEmail;
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
                'is_admin' => true,
                'is_event_organizer' => $managedUser->is_event_organizer,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertTrue($managedUser->fresh()->is_admin);
    }

    #[Test]
    public function admin_can_edit_user_without_providing_a_password(): void
    {
        $admin = User::factory()->admin()->create();
        $managedUser = User::factory()->create([
            'name' => 'Original Name',
        ]);

        Livewire::actingAs($admin)
            ->test(EditUser::class, ['record' => $managedUser->id])
            ->assertFormFieldDoesNotExist('password')
            ->fillForm([
                'name' => 'Updated Name',
                'nickname' => $managedUser->nickname,
                'email' => $managedUser->email,
                'is_admin' => $managedUser->is_admin,
                'is_event_organizer' => $managedUser->is_event_organizer,
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $this->assertSame('Updated Name', $managedUser->fresh()->name);
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

    #[Test]
    public function users_table_shows_sortable_sent_emails_count(): void
    {
        $admin = User::factory()->admin()->create();
        $userWithEmails = User::factory()->create();
        $userWithoutEmails = User::factory()->create();

        SentEmail::factory()->count(2)->create([
            'recipient_user_id' => $userWithEmails->id,
        ]);

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertCanSeeTableRecords([$userWithEmails, $userWithoutEmails])
            ->assertSee('2')
            ->sortTable('sent_emails_count', 'desc')
            ->assertCanSeeTableRecords([$userWithEmails, $userWithoutEmails], inOrder: true);
    }
}
