<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Domain\ActivityBadges\ActivityBadgeGroupBuilder;
use App\Domain\ActivityBadges\ActivityBadgeKind;
use App\Enums\BadgeSemantic;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
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
    public function from_activity_exposes_participants_and_kind_corner_label(): void
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
        $activity->setRelation('creator', $user);

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertSame('activity', $viewData->kind);
        $this->assertSame('ui-card-activity', $viewData->cardModifierClass);
        $this->assertSame('activity-card', $viewData->dataUiPrefix);
        $this->assertTrue($viewData->showParticipants);
        $this->assertSame(6, $viewData->participantsMax);
        $this->assertSame('Tavern Hall', $viewData->locationSummary);
        $this->assertSame(__('ui.browse.listing_kind_activity'), $viewData->kindCornerLabel);
        $this->assertSame($user->id, $viewData->hostUser?->id);
        $this->assertNull($viewData->hostOrganization);
        $this->assertNull($viewData->parentEventName);
        $this->assertNull($viewData->parentEventUrl);
        $this->assertSame('toggleActivityInterest', $viewData->interestWireMethod);
        $this->assertFalse($viewData->isInterested);
    }

    #[Test]
    public function from_activity_uses_venue_name_when_slot_place_is_a_room(): void
    {
        $user = User::factory()->create();
        $venue = Place::factory()->venue()->create(['name' => 'Convention Center']);
        $room = Place::factory()->room($venue)->create(['name' => 'Hall A']);
        $event = Event::factory()->create(['created_by' => $user->id, 'name' => 'Mega Con']);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
            'place_id' => $room->id,
        ]);
        $slot->setRelation('place', $room->load('parent'));
        $slot->setRelation('event', $event);
        $activity->setRelation('slot', $slot);

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertSame('Convention Center', $viewData->locationSummary);
        $this->assertNotSame('Hall A', $viewData->locationSummary);
        $this->assertSame('Mega Con', $viewData->parentEventName);
        $this->assertSame(route('events.show', $event), $viewData->parentEventUrl);
    }

    #[Test]
    public function from_self_hosted_activity_has_no_parent_event(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertNull($viewData->parentEventName);
        $this->assertNull($viewData->parentEventUrl);
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
            'organization_id' => null,
            'name' => 'Mega Con',
            'starts_at' => $startsAt,
            'ends_at' => $endsAt,
        ]);
        $event->places()->attach($place->id);
        $event->load('places.city');
        $event->setRelation('creator', $user);

        $viewData = $this->presenter->fromEvent($event, [(int) $event->id]);

        $this->assertSame('event', $viewData->kind);
        $this->assertSame('ui-card-event', $viewData->cardModifierClass);
        $this->assertSame('event-card', $viewData->dataUiPrefix);
        $this->assertFalse($viewData->showParticipants);
        $this->assertSame(__('ui.browse.listing_kind_event'), $viewData->kindCornerLabel);
        $this->assertSame($user->id, $viewData->hostUser?->id);
        $this->assertNull($viewData->hostOrganization);
        $this->assertNull($viewData->parentEventName);
        $this->assertSame('Convention Center', $viewData->locationSummary);
        $this->assertSame('toggleEventInterest', $viewData->interestWireMethod);
        $this->assertTrue($viewData->isInterested);
    }

    #[Test]
    public function from_event_exposes_host_organization_when_set(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Guild HQ']);
        $event = Event::factory()->create([
            'created_by' => $user->id,
            'organization_id' => $organization->id,
        ]);
        $event->setRelation('creator', $user);
        $event->setRelation('organization', $organization);

        $viewData = $this->presenter->fromEvent($event, []);

        $this->assertSame($organization->id, $viewData->hostOrganization?->id);
        $this->assertSame($user->id, $viewData->hostUser?->id);
    }

    #[Test]
    public function from_event_slot_type_badges_use_config_activity_type_semantic(): void
    {
        $activityType = ActivityType::query()->where('slug', ActivityType::SLUG_RPG)->first()
            ?? ActivityType::factory()->create(['slug' => ActivityType::SLUG_RPG]);
        $event = Event::factory()->create();
        $slot = Slot::factory()->create(['event_id' => $event->id]);
        $slot->activityTypes()->attach($activityType->id);
        $slot->load('activityTypes');
        $event->setRelation('slots', collect([$slot]));

        $viewData = $this->presenter->fromEvent($event, []);

        $this->assertCount(1, $viewData->badgeItems);
        $this->assertSame(ActivityBadgeKind::ActivityType, $viewData->badgeItems[0]->kind);
        $this->assertSame(BadgeSemantic::Secondary, $viewData->badgeItems[0]->semantic);
    }
}
