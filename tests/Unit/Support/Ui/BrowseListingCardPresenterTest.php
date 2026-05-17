<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Models\Activity;
use App\Models\Event;
use App\Models\Place;
use App\Models\User;
use App\Support\Ui\BrowseListingCardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class BrowseListingCardPresenterTest extends TestCase
{
    use RefreshDatabase;

    private BrowseListingCardPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = new BrowseListingCardPresenter(new ActivityBadgeGroupBuilder);
    }

    #[Test]
    public function from_activity_exposes_participants_and_hosting_corner_label(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create(['name' => 'Tavern Hall']);
        $startsAt = now()->addWeek()->setSecond(0);
        $endsAt = (clone $startsAt)->addHours(3);

        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'place_id' => $place->id,
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
            'name' => 'Draft Night',
            'max_participants' => 6,
        ]);
        $activity->setRelation('place', $place);

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertSame('activity', $viewData->kind);
        $this->assertSame('ui-card-activity', $viewData->cardModifierClass);
        $this->assertSame('activity-card', $viewData->dataUiPrefix);
        $this->assertTrue($viewData->showParticipants);
        $this->assertSame(6, $viewData->participantsMax);
        $this->assertSame('Tavern Hall', $viewData->locationSummary);
        $this->assertSame(__('ui.activities.hosting_modes.draft'), $viewData->hostingCornerLabel);
        $this->assertSame('toggleActivityInterest', $viewData->interestWireMethod);
        $this->assertFalse($viewData->isInterested);
    }

    #[Test]
    public function from_activity_marks_interested_when_id_is_listed(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $viewData = $this->presenter->fromActivity($activity, [(int) $activity->id]);

        $this->assertTrue($viewData->isInterested);
    }

    #[Test]
    public function from_event_uses_compact_place_summary_and_event_interest_toggle(): void
    {
        $user = User::factory()->create();
        $place = Place::factory()->venue()->create(['name' => 'Convention Center']);
        $startsAt = now()->addDays(10)->setSecond(0);
        $endsAt = (clone $startsAt)->addDays(2);

        $event = Event::factory()->create([
            'created_by' => $user->id,
            'name' => 'Mega Con',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $event->places()->attach($place->id);
        $event->load('places.city');

        $viewData = $this->presenter->fromEvent($event, [(int) $event->id]);

        $this->assertSame('event', $viewData->kind);
        $this->assertSame('ui-card-event', $viewData->cardModifierClass);
        $this->assertSame('event-card', $viewData->dataUiPrefix);
        $this->assertFalse($viewData->showParticipants);
        $this->assertNull($viewData->hostingCornerLabel);
        $this->assertSame('Convention Center', $viewData->locationSummary);
        $this->assertSame('toggleEventInterest', $viewData->interestWireMethod);
        $this->assertTrue($viewData->isInterested);
    }
}
