<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PageHeaderComponentTest extends TestCase
{
    public function test_page_header_renders_title(): void
    {
        $html = Blade::render('<x-page-header title="Szept w Ciemności" />');

        $this->assertStringContainsString('<h1', $html);
        $this->assertStringContainsString('Szept w Ciemności', $html);
    }

    public function test_page_header_renders_user_badge_when_user_is_provided(): void
    {
        $user = User::factory()->create();

        $html = Blade::render('<x-page-header title="Test" :user="$user" />', [
            'user' => $user,
        ]);

        $this->assertStringContainsString($user->nickname, $html);
        $this->assertStringContainsString('data-ui="activity-show-host"', $html);
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
