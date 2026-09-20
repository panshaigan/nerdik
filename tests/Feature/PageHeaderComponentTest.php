<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Blade;
use Tests\TestCase;

class PageHeaderComponentTest extends TestCase
{
    use RefreshDatabase;

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

    public function test_page_header_renders_subtitle_before_user_badge(): void
    {
        $user = User::factory()->create(['nickname' => 'HeaderBadgeNick']);

        $html = Blade::render('
            <x-page-header title="Test" :user="$user">
                <x-slot:subtitle>Under the title</x-slot:subtitle>
            </x-page-header>
        ', [
            'user' => $user,
        ]);

        $subtitlePos = strpos($html, 'Under the title');
        $badgePos = strpos($html, 'HeaderBadgeNick');

        $this->assertNotFalse($subtitlePos);
        $this->assertNotFalse($badgePos);
        $this->assertLessThan($badgePos, $subtitlePos);
    }

    public function test_page_header_renders_info_slot(): void
    {
        $html = Blade::render('
            <x-page-header title="Test">
                <x-slot:info>Header info tiles</x-slot:info>
            </x-page-header>
        ');

        $this->assertStringContainsString('data-ui="page-header-info"', $html);
        $this->assertStringContainsString('Header info tiles', $html);
    }

    public function test_page_header_omits_info_when_info_slot_is_missing(): void
    {
        $html = Blade::render('<x-page-header title="Test" />');

        $this->assertStringNotContainsString('data-ui="page-header-info"', $html);
    }
}
