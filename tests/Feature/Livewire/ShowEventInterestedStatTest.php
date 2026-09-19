<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use App\Services\UserInterestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventInterestedStatTest extends TestCase
{
    use RefreshDatabase;

    public function test_add_and_remove_interest_update_event_interest_relation(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('addInterest');

        $this->assertTrue(
            $user->fresh()->interestedEvents()->whereKey($event->id)->exists()
        );

        Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('removeInterest');

        $this->assertFalse(
            $user->fresh()->interestedEvents()->whereKey($event->id)->exists()
        );
    }

    public function test_toolbar_interest_button_includes_wire_click_when_authenticated(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSeeHtml('data-ui="event-show-interest-add"')
            ->assertSeeHtml('wire:click="addInterest"');
    }

    public function test_guest_sees_follow_count_without_toggle(): void
    {
        $event = Event::factory()->create();

        $html = Livewire::test(ShowEvent::class, ['event' => $event])->html();

        $this->assertStringContainsString('data-ui="event-show-interest-count"', $html);
        $this->assertStringNotContainsString('data-ui="event-show-interest-add"', $html);
        $this->assertStringNotContainsString('data-ui="event-show-interest-remove"', $html);
        $this->assertStringNotContainsString('wire:click="addInterest"', $html);
        $this->assertStringNotContainsString('wire:click="removeInterest"', $html);
    }

    public function test_toolbar_interest_buttons_toggle_for_authenticated_user(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSeeHtml('data-ui="event-show-interest-add"')
            ->assertDontSeeHtml('data-ui="event-show-interest-remove"');

        $component->call('addInterest')
            ->assertSeeHtml('data-ui="event-show-interest-remove"')
            ->assertDontSeeHtml('data-ui="event-show-interest-add"');

        $component->call('removeInterest')
            ->assertSeeHtml('data-ui="event-show-interest-add"')
            ->assertDontSeeHtml('data-ui="event-show-interest-remove"');
    }

    public function test_toolbar_follow_count_updates_after_interest_toggle(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $component = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSeeHtml('data-ui="event-show-interest-count"')
            ->assertSeeHtml('data-count="0"');

        $component->call('addInterest')
            ->assertSeeHtml('data-count="1"');

        $component->call('removeInterest')
            ->assertSeeHtml('data-count="0"');
    }

    public function test_shell_follow_count_updates_after_plan_tab_activity_interest_sync(): void
    {
        config(['cache.default' => 'array']);

        $user = User::factory()->create();
        $event = Event::factory()->public()->create();
        $activity = Activity::factory()->scheduled()->create();
        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $component = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->assertSeeHtml('data-ui="event-show-interest-add"')
            ->assertSeeHtml('data-count="0"');

        app(UserInterestService::class)->addActivityInterest($user, $activity);

        $component
            ->call('refreshShellFromNestedTabs')
            ->assertSeeHtml('data-ui="event-show-interest-remove"')
            ->assertSeeHtml('data-count="1"');
    }
}
