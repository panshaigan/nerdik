<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Livewire;

use App\Support\Livewire\SpaNavigate;
use Illuminate\Support\Facades\Config;
use Tests\TestCase;

final class SpaNavigateTest extends TestCase
{
    public function test_front_app_paths_allow_spa_navigate(): void
    {
        Config::set('filament.admin_path', 'secret-admin');
        Config::set('pulse.path', 'pulse');

        $this->assertTrue(SpaNavigate::allowed('/dashboard'));
        $this->assertTrue(SpaNavigate::allowed('/profile'));
        $this->assertTrue(SpaNavigate::allowed('/events/1'));
        $this->assertFalse(SpaNavigate::requiresFullPageLoad('/notifications'));
    }

    public function test_filament_and_pulse_paths_require_full_page_load(): void
    {
        Config::set('filament.admin_path', 'secret-admin');
        Config::set('pulse.path', 'pulse');

        $this->assertTrue(SpaNavigate::requiresFullPageLoad('/secret-admin'));
        $this->assertTrue(SpaNavigate::requiresFullPageLoad('/secret-admin/feedback/2'));
        $this->assertTrue(SpaNavigate::requiresFullPageLoad('/secret-admin/feedback/2?tab=info'));
        $this->assertFalse(SpaNavigate::allowed('/pulse'));
        $this->assertFalse(SpaNavigate::allowed('/pulse/queues'));
    }

    public function test_similar_prefixes_do_not_match_as_admin_panel(): void
    {
        Config::set('filament.admin_path', 'admin');
        Config::set('pulse.path', 'pulse');

        $this->assertTrue(SpaNavigate::allowed('/administration'));
        $this->assertTrue(SpaNavigate::allowed('/admin-extra'));
        $this->assertTrue(SpaNavigate::allowed('/pulse-check'));
    }

    public function test_full_page_path_prefixes_are_normalized_for_client_guard(): void
    {
        Config::set('filament.admin_path', 'secret-admin');
        Config::set('pulse.path', 'pulse');

        $this->assertSame(['/secret-admin', '/pulse'], SpaNavigate::fullPagePathPrefixes());
    }
}
