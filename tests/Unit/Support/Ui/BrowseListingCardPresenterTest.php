<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Domain\ActivityBadges\ActivityBadgeKind;
use App\Enums\ActivityLogoSource;
use App\Enums\BadgeSemantic;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\City;
use App\Models\CityTranslation;
use App\Models\Country;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\User;
use App\Support\Ui\BrowseListingCardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AttachesFixtureMedia;
use Tests\Support\SeedsListingDefaultMedia;
use Tests\TestCase;

final class BrowseListingCardPresenterTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;
    use SeedsListingDefaultMedia;

    private BrowseListingCardPresenter $presenter;

    protected function setUp(): void
    {
        parent::setUp();
        $this->presenter = app(BrowseListingCardPresenter::class);
    }

    #[Test]
    public function from_activity_includes_city_in_location_summary(): void
    {
        $user = User::factory()->create();
        $city = $this->createCity('Wroclaw');
        $place = Place::factory()->venue()->create([
            'name' => 'Tavern Hall',
            'city_id' => $city->id,
        ]);
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'place_id' => $place->id,
        ]);
        $activity->setRelation('place', $place->load('city'));

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertSame('Tavern Hall (Wroclaw)', $viewData->locationSummary);
    }

    #[Test]
    public function from_activity_exposes_participants_without_kind_corner_label(): void
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
        $this->assertSame('', $viewData->kindCornerLabel);
        $this->assertSame($user->id, $viewData->hostUser?->id);
        $this->assertNull($viewData->hostOrganization);
        $this->assertNull($viewData->parentEventName);
        $this->assertNull($viewData->parentEventUrl);
        $this->assertSame('toggleActivityInterest', $viewData->interestWireMethod);
        $this->assertFalse($viewData->isInterested);
        $this->assertFalse($viewData->showDetailsLink);
        $this->assertFalse($viewData->hasActiveEnrollmentWindow);
    }

    #[Test]
    public function from_scheduled_activity_exposes_show_details_link(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->public()->create(['created_by' => $user->id, 'name' => 'Mega Con']);
        $activity = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        $slot = Slot::factory()->create([
            'event_id' => $event->id,
            'activity_id' => $activity->id,
        ]);
        $slot->setRelation('event', $event);
        $activity->setRelation('slot', $slot);

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertTrue($viewData->showDetailsLink);
    }

    #[Test]
    public function from_event_exposes_show_details_link(): void
    {
        $event = Event::factory()->create();

        $viewData = $this->presenter->fromEvent($event, []);

        $this->assertTrue($viewData->showDetailsLink);
    }

    #[Test]
    public function from_activity_uses_venue_name_when_slot_place_is_a_room(): void
    {
        $user = User::factory()->create();
        $city = $this->createCity('Wroclaw');
        $venue = Place::factory()->venue()->create([
            'name' => 'Convention Center',
            'city_id' => $city->id,
        ]);
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
        $slot->setRelation('place', $room->load(['parent', 'city']));
        $slot->setRelation('event', $event);
        $activity->setRelation('slot', $slot);

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertSame('Convention Center (Wroclaw)', $viewData->locationSummary);
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
    public function from_event_exposes_confirmed_activities_count_using_programme_stats(): void
    {
        $user = User::factory()->create();
        $event = Event::factory()->create(['created_by' => $user->id]);
        $active = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'cancelled_at' => null,
        ]);
        $cancelled = Activity::factory()->scheduled()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'cancelled_at' => now(),
        ]);

        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $active->id]);
        Slot::factory()->create(['event_id' => $event->id, 'activity_id' => $cancelled->id]);

        $viewData = $this->presenter->fromEvent($event, []);

        $this->assertSame(1, $viewData->confirmedActivitiesCount);
    }

    #[Test]
    public function from_activity_does_not_expose_confirmed_activities_count(): void
    {
        $activity = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
        ]);

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertNull($viewData->confirmedActivitiesCount);
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
    public function from_activity_includes_cover_picture_with_user_selected_media(): void
    {
        $tag = Tag::factory()->create();
        $media = $this->attachTagSampleMedia($tag, 'tests/fixtures/presenter-cover.jpg');

        $activity = Activity::factory()->create([
            'logo_source' => ActivityLogoSource::Tag,
            'tag_media_id' => $media->id,
        ]);
        $activity->setRelation('tagMedia', $media);

        $viewData = $this->presenter->fromActivity($activity, []);

        $this->assertNotNull($viewData->coverPicture->sources);
    }

    #[Test]
    public function from_event_includes_cover_picture(): void
    {
        $this->seedListingDefaultMedia();

        $event = Event::factory()->create();

        $viewData = $this->presenter->fromEvent($event, []);

        $this->assertTrue($viewData->coverPicture->hasDisplayableImage());
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
        $this->assertSame(BadgeSemantic::Accent, $viewData->badgeItems[0]->semantic);
    }

    #[Test]
    public function from_event_exposes_active_enrollment_window_when_one_is_open(): void
    {
        $event = Event::factory()->create();
        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->addHour(),
        ]);

        $viewData = $this->presenter->fromEvent($event, []);

        $this->assertTrue($viewData->hasActiveEnrollmentWindow);
    }

    #[Test]
    public function from_event_does_not_expose_active_enrollment_window_when_only_closed_windows_exist(): void
    {
        $event = Event::factory()->create();
        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->subDays(2),
            'ends_at' => now()->subDay(),
        ]);
        EventEnrollmentWindow::factory()->create([
            'event_id' => $event->id,
            'starts_at' => now()->addDay(),
            'ends_at' => now()->addDays(2),
        ]);

        $viewData = $this->presenter->fromEvent($event, []);

        $this->assertFalse($viewData->hasActiveEnrollmentWindow);
    }

    private function createCity(string $name): City
    {
        $country = Country::query()->create(['iso_alpha2' => 'PL']);
        $city = City::factory()->create([
            'country_id' => $country->id,
        ]);
        CityTranslation::query()->create([
            'city_id' => $city->id,
            'locale' => app()->getLocale(),
            'name' => $name,
        ]);

        return $city;
    }
}
