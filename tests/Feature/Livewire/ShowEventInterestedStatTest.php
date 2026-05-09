<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Livewire\Events\ShowEvent;
use App\Models\Event;
use App\Models\User;
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

    public function test_interested_stat_includes_wire_click_when_authenticated(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create();

        $html = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->html();

        $opening = $this->interestedStatOpeningTag($html);
        $this->assertNotNull($opening);
        $this->assertStringContainsString('wire:click="addInterest"', $opening);
        $this->assertStringContainsString('wire:target="addInterest, removeInterest"', $opening);
        $this->assertStringContainsString('wire:loading.class.delay="pointer-events-none cursor-wait"', $opening);

        $this->assertStringContainsString('loading loading-spinner loading-sm', $html);

        $htmlAfterAdd = Livewire::actingAs($user)
            ->test(ShowEvent::class, ['event' => $event])
            ->call('addInterest')
            ->html();

        $openingAfter = $this->interestedStatOpeningTag($htmlAfterAdd);
        $this->assertNotNull($openingAfter);
        $this->assertStringContainsString('wire:click="removeInterest"', $openingAfter);
        $this->assertStringContainsString('wire:target="addInterest, removeInterest"', $openingAfter);
    }

    public function test_interested_stat_has_no_wire_click_for_guest(): void
    {
        $event = Event::factory()->create();

        $html = Livewire::test(ShowEvent::class, ['event' => $event])->html();

        $opening = $this->interestedStatOpeningTag($html);
        $this->assertNotNull($opening);
        $this->assertStringNotContainsString('wire:click', $opening);
        $this->assertStringNotContainsString('wire:target', $opening);
    }

    /**
     * @return non-empty-string|null
     */
    private function interestedStatOpeningTag(string $html): ?string
    {
        if (preg_match('/<div\b[^>]*\bdata-ui="event-show-interested-stat"[^>]*>/', $html, $matches) !== 1) {
            return null;
        }

        /** @var non-empty-string */
        return $matches[0];
    }
}
