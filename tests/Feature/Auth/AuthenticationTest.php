<?php

namespace Tests\Feature\Auth;

use App\Enums\AppLocale;
use App\Events\SessionInvalidated;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Livewire\Volt\Volt;
use Tests\TestCase;

class AuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_screen_can_be_rendered(): void
    {
        $response = $this->get('/login');

        $response
            ->assertOk()
            ->assertSeeVolt('pages.auth.login')
            ->assertSee(__('ui.auth.not_registered'))
            ->assertSee(route('register'), false)
            ->assertSee('wire:ignore', false)
            ->assertSee('id="ui-auth-login-submit"', false)
            ->assertSee('x-bind:disabled="isLocked"', false)
            ->assertSee('x-on:submit="submitting = true"', false);
    }

    public function test_users_can_authenticate_using_the_login_screen(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password');

        $component->call('login');

        $component
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
    }

    public function test_login_learns_current_locale_when_user_has_none_stored(): void
    {
        $user = User::factory()->create(['locale' => null]);

        session(['locale' => 'pl']);
        app()->setLocale('pl');

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame(AppLocale::Pl, $user->fresh()->locale);
        $this->assertSame('pl', session('locale'));
    }

    public function test_login_applies_stored_locale_to_session(): void
    {
        $user = User::factory()->locale(AppLocale::Pl)->create();

        session(['locale' => 'en']);
        app()->setLocale('en');

        Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'password')
            ->call('login')
            ->assertHasNoErrors()
            ->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->assertSame(AppLocale::Pl, $user->fresh()->locale);
        $this->assertSame('pl', session('locale'));
    }

    public function test_users_can_not_authenticate_with_invalid_password(): void
    {
        $user = User::factory()->create();

        $component = Volt::test('pages.auth.login')
            ->set('form.email', $user->email)
            ->set('form.password', 'wrong-password');

        $component->call('login');

        $component
            ->assertHasErrors()
            ->assertNoRedirect()
            ->assertSee('id="ui-auth-login-submit"', false)
            ->assertSee('wire:ignore', false);

        $this->assertGuest();
    }

    public function test_navigation_menu_can_be_rendered(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        $response = $this->get('/dashboard');

        $response
            ->assertOk()
            ->assertSeeVolt('layout.navigation');
    }

    public function test_users_can_logout(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user);

        Event::fake([SessionInvalidated::class]);

        $component = Volt::test('layout.navigation');

        $component->call('logout');

        $component
            ->assertHasNoErrors()
            ->assertRedirect('/');

        $this->assertGuest();

        Event::assertDispatched(
            SessionInvalidated::class,
            fn (SessionInvalidated $event) => $event->userId === $user->id
        );
    }

    public function test_livewire_request_with_x_livewire_header_returns_401_json_when_unauthenticated(): void
    {
        $referer = url('/profile');

        $response = $this
            ->withHeaders([
                'X-Livewire' => '1',
                'Referer' => $referer,
            ])
            ->get('/profile');

        $response
            ->assertStatus(401)
            ->assertJsonFragment(['message' => 'Unauthenticated.']);

        $this->assertSame($referer, session('url.intended'));
    }
}
