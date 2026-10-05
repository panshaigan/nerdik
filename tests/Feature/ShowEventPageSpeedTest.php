<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Event;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ShowEventPageSpeedTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_ssrs_plan_description_with_lcp_image_hints_in_initial_html(): void
    {
        $coverSrc = 'https://example.com/storage/editor/event-cover.jpg';
        $user = User::factory()->create();
        $event = Event::factory()->public()->create([
            'created_by' => $user->id,
            'description' => '<p>Event details.</p><p><img src="'.$coverSrc.'" alt="Event cover" class="float-right" width="353" height="500"></p>',
        ]);

        $response = $this->get(route('events.show', $event));

        $response->assertOk();
        $response->assertSee($coverSrc, false);
        $response->assertSee('fetchpriority="high"', false);
        $response->assertSee('loading="eager"', false);
        $response->assertDontSee('data-ui="event-show-plan-tab-placeholder"', false);
    }
}
