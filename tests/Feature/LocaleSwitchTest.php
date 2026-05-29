<?php

namespace Tests\Feature;

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

    public function test_navigation_locale_links_use_wire_navigate_instead_of_full_reload(): void
    {
        Volt::test('layout.navigation')
            ->assertSee('wire:navigate', false)
            ->assertSee('localeSwitchUrl(', false)
            ->assertDontSee(
                "window.location.href = '".route('locale.switch', ['locale' => 'en'])."'",
                false,
            );
    }
}
