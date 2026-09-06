<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PageHeaderComponentTest extends TestCase
{
    public function test_page_header_renders_title_with_display_font_and_glow(): void
    {
        $html = Blade::render('<x-page-header title="Szept w Ciemności" />');

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Szept w Ciemności', $html);
        $this->assertStringContainsString('font-display', $html);
        $this->assertStringContainsString('px-4', $html);
        $this->assertStringContainsString('sm:px-6', $html);
        $this->assertStringContainsString('lg:px-8', $html);
    }

    public function test_page_header_renders_user_badge_when_user_is_provided(): void
    {
        $user = User::factory()->create();

        $html = Blade::render('<x-page-header title="Test" :user="$user" />', [
            'user' => $user,
        ]);

        $this->assertStringContainsString($user->nickname, $html);
        $this->assertStringContainsString('data-ui="activity-show-host"', $html);
        $this->assertStringContainsString('flex-col items-start gap-3 sm:flex-row', $html);
        $this->assertStringContainsString('w-full sm:w-auto', $html);
    }

    public function test_page_header_renders_back_button_when_back_url_is_set(): void
    {
        $html = Blade::render('<x-page-header title="Test" back-url="/events" />');

        $this->assertStringContainsString('data-ui="page-header-back"', $html);
        $this->assertStringContainsString('href="/events"', $html);
    }

    public function test_page_header_renders_subtitle_slot_inside_wrapper(): void
    {
        $html = Blade::render('
            <x-page-header title="Test">
                <x-slot:subtitle>TTRPG 3H</x-slot:subtitle>
            </x-page-header>
        ');

        $this->assertStringContainsString('TTRPG 3H', $html);
    }
}
