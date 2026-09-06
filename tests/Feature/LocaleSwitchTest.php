<?php

namespace Tests\Feature;

use App\Enums\AppLocale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Volt\Volt;
use Tests\TestCase;

class LocaleSwitchTest extends TestCase
{
    use RefreshDatabase;

    public function test_locale_switch_sets_session_and_redirects_to_requested_path(): void
    {
        $response = $this->get(route('locale.switch', [
            'locale' => 'pl',
            'redirect' => '/search?q=test',
        ]));

        $response
            ->assertRedirect('/search?q=test')
            ->assertCookie('locale', 'pl');

        $this->assertSame('pl', session('locale'));
    }

    public function test_locale_switch_rejects_unsupported_locale(): void
    {
        $this->get(route('locale.switch', ['locale' => 'de']))
            ->assertNotFound();
    }

    public function test_locale_switch_falls_back_to_dashboard_for_unsafe_redirect(): void
    {
        $this->get(route('locale.switch', [
            'locale' => 'en',
            'redirect' => 'https://evil.test/phish',
        ]))
            ->assertRedirect(route('dashboard'));
    }

    public function test_authenticated_locale_switch_persists_to_user(): void
    {
        $user = User::factory()->create(['locale' => null]);

        $this->actingAs($user)
            ->get(route('locale.switch', [
                'locale' => 'pl',
                'redirect' => '/search?q=test',
            ]))
            ->assertRedirect('/search?q=test')
            ->assertCookie('locale', 'pl');

        $this->assertSame(AppLocale::Pl, $user->fresh()->locale);
        $this->assertSame('pl', session('locale'));
    }

    public function test_guest_locale_switch_does_not_require_a_user(): void
    {
        $this->get(route('locale.switch', [
            'locale' => 'pl',
            'redirect' => '/search',
        ]))
            ->assertRedirect('/search')
            ->assertCookie('locale', 'pl');

        $this->assertSame('pl', session('locale'));
        $this->assertSame(0, User::query()->where('locale', AppLocale::Pl->value)->count());
    }

    public function test_navigation_locale_toggle_uses_wire_navigate_and_targets_other_locale(): void
    {
        app()->setLocale('en');

        Volt::test('layout.navigation')
            ->assertSee('wire:navigate', false)
            ->assertSee('localeSwitchUrl(', false)
            ->assertSee(route('locale.switch', ['locale' => 'pl']), false)
            ->assertDontSee(route('locale.switch', ['locale' => 'en']), false)
            ->assertDontSee(
                "window.location.href = '".route('locale.switch', ['locale' => 'en'])."'",
                false,
            );
    }

    public function test_navigation_locale_toggle_targets_english_when_polish_is_active(): void
    {
        app()->setLocale('pl');

        Volt::test('layout.navigation')
            ->assertSee(route('locale.switch', ['locale' => 'en']), false)
            ->assertDontSee(route('locale.switch', ['locale' => 'pl']), false);
    }
}
