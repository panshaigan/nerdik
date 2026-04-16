<?php

namespace Database\Seeders;

use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Random\RandomException;

use function fake;

/**
 * Generate sample/test data
 */
class SampleDataSeeder extends Seeder
{
    public const DATA_SET_MINIMAL = 1;
    public const DATA_SET_STANDARD = 2;
    public const DATA_SET_MAXIMAL = 3;

    public const DATA_SETS = [
        self::DATA_SET_MINIMAL => [
            'admins' => 1,
            'organizers' => 2,
            'standardUsers' => 10,
            'organizations' => 10,
            'places' => 10,
            'maxRoomsPerVenue' => 2,
            'events' => 5,
            'minSlotsPerEvent' => 6,
            'maxSlotsPerEvent' => 10,
            'selfHostedActivities' => 10,
            'draftActivities' => 10,
            'scheduledActivities' => 30,
            'proposedActivities' => 50,
        ],
        self::DATA_SET_STANDARD => [
            'admins' => 2,
            'organizers' => 4,
            'standardUsers' => 20,
            'organizations' => 20,
            'places' => 20,
            'maxRoomsPerVenue' => 4,
            'events' => 10,
            'minSlotsPerEvent' => 6,
            'maxSlotsPerEvent' => 20,
            'selfHostedActivities' => 20,
            'draftActivities' => 20,
            'scheduledActivities' => 60,
            'proposedActivities' => 100,
        ],
        self::DATA_SET_MAXIMAL => [
            'admins' => 4,
            'organizers' => 8,
            'standardUsers' => 40,
            'organizations' => 30,
            'places' => 30,
            'maxRoomsPerVenue' => 6,
            'events' => 20,
            'minSlotsPerEvent' => 6,
            'maxSlotsPerEvent' => 30,
            'selfHostedActivities' => 40,
            'draftActivities' => 40,
            'scheduledActivities' => 120,
            'proposedActivities' => 200,
        ],
    ];

    /**
     * Seed sample data for local testing: users, orgs, events, slots, activities, proposals.
     * All entities get created_by set. Safe to run multiple times (use firstOrCreate by slug/email).
     * @throws RandomException
     */
    public function run(int $chosenDataset = self::DATA_SET_MINIMAL): void
    {
        $dataset = self::DATA_SETS[$chosenDataset];
        $this->callWith(UserSeeder::class, ['dataset' => $dataset]);
        $this->callWith(PlaceSeeder::class, ['dataset' => $dataset]);

        $activityTypes = ActivityType::all();
        $tags          = Tag::all();
        $organizers    = User::where('is_event_organizer', 1)->get();
        $allUsers      = User::all();
        $venues        = Place::where('type', Place::TYPE_VENUE)->get();

        $organizations = Organization::factory($dataset['organizations'])
            ->recycle($allUsers)
            ->predefined()
            ->create();

        $events = Event::factory($dataset['events'])
            ->public()
            ->recycle($organizations)
            ->recycle($organizers)
            ->recycle($venues)
            ->predefined()
            ->withSameCreatorAsOrganization()
            ->has(EventEnrollmentWindow::factory()->consistentWithEvent())
            ->withSlots(fake()->numberBetween($dataset['minSlotsPerEvent'], $dataset['maxSlotsPerEvent']), $activityTypes)
            ->withVenues($venues)
            ->withRandomRooms()
            ->create();

        Activity::factory($dataset['selfHostedActivities'])
            ->recycle($allUsers)
            ->predefined()
            ->selfHosted($allUsers)
            ->create();

        Activity::factory($dataset['draftActivities'])
            ->recycle($allUsers)
            ->predefined()
            ->create();

        $proposedActivities = Activity::factory($dataset['proposedActivities'])
            ->recycle($allUsers)
            ->predefined()
            ->proposed()
            ->create();

        $scheduledActivities = Activity::factory($dataset['scheduledActivities'])
            ->recycle($allUsers)
            ->predefined()
            ->proposed()
            ->create();

        foreach ($proposedActivities as $activity) {
            ActivityProposal::factory()
                ->recycle($events->random())
                ->recycle($activity)
                ->recycle($activity->creator)
                ->create();
        }
    }
}
