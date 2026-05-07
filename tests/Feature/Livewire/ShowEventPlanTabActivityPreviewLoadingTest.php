<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShowEventPlanTabActivityPreviewLoadingTest extends TestCase
{
    use RefreshDatabase;

    public function test_plan_tab_renders_activity_preview_loading_markers_on_attached_slot(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->create(['created_by' => $owner->id, 'updated_by' => $owner->id]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);

        $activityId = (int) $activity->id;

        Livewire::actingAs($owner)
            ->test(ShowEvent::class, ['event' => $event])
            ->set('tab', 'plan')
            ->assertSeeHtml('wire:target="openActivityPreview('.$activityId.')"')
            ->assertSeeHtml('wire:loading.attr="disabled"')
            ->assertSeeHtml('wire:loading.delay')
            ->assertSeeHtml('loading loading-spinner loading-lg');
    }
}
