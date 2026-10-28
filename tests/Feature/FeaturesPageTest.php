<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\User;
use App\Support\Seo\Seo;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class FeaturesPageTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function guest_can_view_features_page(): void
    {
        app()->setLocale('en');

        $response = $this->get(route('features'));

        $response->assertOk();
        $response->assertSee(__('ui.features.page_title'), false);
        $response->assertSee(__('ui.features.tab_players'), false);
        $response->assertSee(__('ui.features.tab_hosts'), false);
        $response->assertSee(__('ui.features.tab_organizers'), false);
        $response->assertSee(__('ui.features.players_open_join_title'), false);
        $response->assertSee(__('ui.features.hosts_activity_types_title'), false);
        $response->assertSee(__('ui.features.organizers_slots_title'), false);
        $response->assertSee('<title>'.Seo::pageTitle((string) __('ui.seo.features_title')).'</title>', false);
        $response->assertSee('<link rel="canonical" href="'.e(route('features')).'">', false);
    }

    #[Test]
    public function authenticated_user_can_view_features_page(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('features'))
            ->assertOk()
            ->assertSee(__('ui.features.cta_dashboard'), false);
    }

    #[Test]
    public function features_page_renders_in_polish_when_locale_is_set(): void
    {
        $response = $this->withSession(['locale' => 'pl'])->get(route('features'));

        $response->assertOk();
        app()->setLocale('pl');
        $response->assertSee(__('ui.features.page_title'), false);
    }

    #[Test]
    public function tab_query_selects_initial_panel(): void
    {
        $response = $this->get(route('features', ['tab' => 'organizers']));

        $response->assertOk();
        $response->assertSee('tab: "organizers"', false);
    }
}
