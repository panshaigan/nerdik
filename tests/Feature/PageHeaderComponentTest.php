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
        $this->assertStringContainsString('text-glow-primary', $html);
    }

    public function test_page_header_renders_user_badge_when_user_is_provided(): void
    {
        $user = User::factory()->create([
            'nickname' => 'kuphal.felicia',
        ]);

        $html = Blade::render('<x-page-header title="Test" :user="$user" />', [
            'user' => $user,
        ]);

        $this->assertStringContainsString('kuphal.felicia', $html);
        $this->assertStringContainsString('data-ui="activity-show-host"', $html);
    }

    public function test_page_header_renders_back_button_when_back_url_is_set(): void
    {
        $html = Blade::render('<x-page-header title="Test" back-url="/events" />');

        $this->assertStringContainsString('data-ui="page-header-back"', $html);
        $this->assertStringContainsString('href="/events"', $html);
    }

    public function test_page_header_renders_hr_between_title_row_and_subtitle(): void
    {
        $html = Blade::render('
            <x-page-header title="Test">
                <x-slot:subtitle>TTRPG 3H</x-slot:subtitle>
            </x-page-header>
        ');

        $this->assertMatchesRegularExpression('/space-y-1/', $html);
        $this->assertMatchesRegularExpression('/<svg[\s\S]*<\/svg>/', $html);

        $titlePos = strpos($html, '<h1');
        $hrPos = strpos($html, 'space-y-1');
        $subtitlePos = strpos($html, 'TTRPG 3H');

        $this->assertNotFalse($titlePos);
        $this->assertNotFalse($hrPos);
        $this->assertNotFalse($subtitlePos);
        $this->assertLessThan($hrPos, $titlePos);
        $this->assertLessThan($subtitlePos, $hrPos);
        $this->assertStringNotContainsString('absolute inset-x-6 top-1/2', $html);
    }

    public function test_page_header_renders_subtitle_slot_inside_wrapper(): void
    {
        $html = Blade::render('
            <x-page-header title="Test">
                <x-slot:subtitle>TTRPG 3H</x-slot:subtitle>
            </x-page-header>
        ');

        $this->assertStringContainsString('TTRPG 3H', $html);
        $this->assertStringContainsString('tracking-widest', $html);
        $this->assertStringContainsString('text-primary/75', $html);
    }
}
