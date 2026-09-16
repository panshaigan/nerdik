<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Livewire\Actions\Logout;
use App\Livewire\Activities\UserContactPopover;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use STS\FilamentImpersonate\Facades\Impersonation;
use Tests\TestCase;

final class ImpersonationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_can_enter_and_leave_impersonation(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);

        $this->assertTrue(Impersonation::enter($admin, $target, config('auth.defaults.guard')));
        $this->assertTrue(Impersonation::isImpersonating());
        $this->assertAuthenticatedAs($target);
        $this->assertSame($admin->id, Impersonation::getImpersonatorId());

        $this->get(route('dashboard'))
            ->assertOk()
            ->assertSee(__('filament-impersonate::banner.leave'), false);

        $this->get(route('filament-impersonate.leave'))
            ->assertRedirect();

        $this->assertFalse(Impersonation::isImpersonating());
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function non_admin_cannot_impersonate_via_contact_popover(): void
    {
        $viewer = User::factory()->create();
        $target = User::factory()->create();

        Livewire::actingAs($viewer)
            ->test(UserContactPopover::class, ['targetUserId' => $target->id])
            ->assertDontSeeHtml('data-ui="user-contact-popover-impersonate"')
            ->call('impersonate')
            ->assertForbidden();

        $this->assertFalse(Impersonation::isImpersonating());
        $this->assertAuthenticatedAs($viewer);
    }

    #[Test]
    public function admin_cannot_impersonate_another_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $otherAdmin = User::factory()->admin()->create();

        $this->assertFalse($otherAdmin->canBeImpersonated());

        Livewire::actingAs($admin)
            ->test(UserContactPopover::class, ['targetUserId' => $otherAdmin->id])
            ->assertDontSeeHtml('data-ui="user-contact-popover-impersonate"')
            ->call('impersonate')
            ->assertForbidden();

        $this->assertFalse(Impersonation::isImpersonating());
        $this->assertAuthenticatedAs($admin);
    }

    #[Test]
    public function admin_contact_popover_impersonate_redirects_to_dashboard(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(UserContactPopover::class, ['targetUserId' => $target->id])
            ->assertSeeHtml('data-ui="user-contact-popover-impersonate"')
            ->call('impersonate')
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Impersonation::isImpersonating());
        $this->assertAuthenticatedAs($target);
    }

    #[Test]
    public function filament_users_table_shows_impersonate_action_for_eligible_targets(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        Livewire::actingAs($admin)
            ->test(ListUsers::class)
            ->assertSuccessful()
            ->assertTableActionVisible('impersonate', $target)
            ->assertTableActionHidden('impersonate', $admin)
            ->callTableAction('impersonate', $target)
            ->assertRedirect(route('dashboard'));

        $this->assertTrue(Impersonation::isImpersonating());
        $this->assertAuthenticatedAs($target);
    }

    #[Test]
    public function oauth_redirect_is_blocked_while_impersonating(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);
        Impersonation::enter($admin, $target, config('auth.defaults.guard'));

        $this->get(route('google.redirect', ['return_tab' => 'contact']))
            ->assertRedirect(route('dashboard'));

        $this->assertEquals(
            __('ui.impersonation.account_mutation_blocked'),
            session('ui.toast.title'),
        );
    }

    #[Test]
    public function password_update_is_blocked_while_impersonating(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create(['password' => 'password']);

        $this->actingAs($admin);
        Impersonation::enter($admin, $target, config('auth.defaults.guard'));

        $originalHash = $target->fresh()->password;

        Livewire::actingAs($target)
            ->test('profile.update-password-form')
            ->set('password', 'new-password-123')
            ->set('password_confirmation', 'new-password-123')
            ->call('updatePassword')
            ->assertHasNoErrors();

        $this->assertSame($originalHash, $target->fresh()->password);
    }

    #[Test]
    public function logout_while_impersonating_ends_session_without_restoring_admin(): void
    {
        $admin = User::factory()->admin()->create();
        $target = User::factory()->create();

        $this->actingAs($admin);
        Impersonation::enter($admin, $target, config('auth.defaults.guard'));
        $this->assertTrue(Impersonation::isImpersonating());

        app(Logout::class)();

        $this->assertGuest();
        $this->assertFalse(Impersonation::isImpersonating());
    }
}
