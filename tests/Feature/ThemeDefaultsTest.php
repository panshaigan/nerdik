<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ThemeDefaultsTest extends TestCase
{
    public function test_guest_layout_defaults_to_dark_theme_before_javascript_runs(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('class="dark" data-theme="dark"', false);
    }

    public function test_theme_script_defaults_to_dark_without_system_preference(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee("saved = 'dark'", false)
            ->assertDontSee('prefers-color-scheme', false);
    }

    public function test_theme_toggle_component_defaults_to_dark_instead_of_system_theme(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee("\$persist('dark').as('mary-theme')", false)
            ->assertDontSee("matchMedia('(prefers-color-scheme: dark)')", false);
    }
}
