<?php

declare(strict_types=1);

namespace Tests\Feature\Ui;

use App\Livewire\Events\EventShowPlanTab;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Slot;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ShowPlanTabSlotToolbarOverflowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function plan_tab_slot_tiles_keep_toolbar_tooltips_unclipped(): void
    {
        $owner = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $owner->id]);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $owner->id,
            'updated_by' => $owner->id,
        ]);

        Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'starts_at' => now()->addHour(),
            'ends_at' => now()->addHours(3),
        ]);

        $html = Livewire::withoutLazyLoading()
            ->actingAs($owner)
            ->test(EventShowPlanTab::class, ['eventId' => $event->id])
            ->html();

        $this->assertStringContainsString('ui-timeline-collapse', $html);
        $this->assertStringContainsString('status-dots-toolbar', $html);
        $this->assertStringContainsString('data-tip="'.__('ui.events.edit_slot').'"', $html);
        $this->assertSlotTileDoesNotUseOverflowHidden($html);
    }

    /**
     * @param  non-empty-string  $html
     */
    private function assertSlotTileDoesNotUseOverflowHidden(string $html): void
    {
        if (preg_match('/<li\b[^>]*\bstatus-dots\b[^>]*>/', $html, $matches) !== 1) {
            $this->fail('Plan tab slot tile element not found in rendered HTML.');
        }

        $this->assertStringContainsString('overflow-visible', $matches[0]);
        $this->assertStringNotContainsString('overflow-hidden', $matches[0]);
    }
}
