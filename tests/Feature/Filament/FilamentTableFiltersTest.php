<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Enums\ActivityProposalStatus;
use App\Enums\ParticipationMode;
use App\Filament\Admin\Resources\Activities\Pages\ListActivities;
use App\Filament\Admin\Resources\ActivityProposals\Pages\ListActivityProposals;
use App\Filament\Admin\Resources\ActivityTypeSlots\Pages\ListActivityTypeSlots;
use App\Filament\Admin\Resources\ActivityUsers\Pages\ListActivityUsers;
use App\Filament\Admin\Resources\EventEnrollmentWindows\Pages\ListEventEnrollmentWindows;
use App\Filament\Admin\Resources\Events\Pages\ListEvents;
use App\Filament\Admin\Resources\Places\Pages\ListPlaces;
use App\Filament\Admin\Resources\Slots\Pages\ListSlots;
use App\Filament\Admin\Resources\TagAliases\Pages\ListTagAliases;
use App\Filament\Admin\Resources\TagCategoryTranslations\Pages\ListTagCategoryTranslations;
use App\Filament\Admin\Resources\TagContexts\Pages\ListTagContexts;
use App\Filament\Admin\Resources\Tags\Pages\ListTags;
use App\Filament\Admin\Resources\TagTranslations\Pages\ListTagTranslations;
use App\Filament\Admin\Resources\Users\Pages\ListUsers;
use App\Models\Activity;
use App\Models\ActivityProposal;
use App\Models\ActivityType;
use App\Models\ActivityTypeSlot;
use App\Models\ActivityUser;
use App\Models\Country;
use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Models\Organization;
use App\Models\Place;
use App\Models\Slot;
use App\Models\Tag;
use App\Models\TagAlias;
use App\Models\TagCategory;
use App\Models\TagCategoryTranslation;
use App\Models\TagContext;
use App\Models\TagTranslation;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class FilamentTableFiltersTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(ActivityTypeSeeder::class);
        $this->admin = User::factory()->admin()->create();
    }

    #[Test]
    public function activities_table_filters_hosting_mode_and_starts_at_range(): void
    {
        $matching = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_SELF_HOSTED,
            'starts_at' => now()->addDay(),
            'created_by' => $this->admin->id,
        ]);
        $other = Activity::factory()->create([
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
            'starts_at' => now()->addDays(20),
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListActivities::class)
            ->filterTable('hosting_mode', Activity::HOSTING_MODE_SELF_HOSTED)
            ->filterTable('starts_at', [
                'starts_at_from' => now()->toDateString(),
                'starts_at_until' => now()->addDays(3)->toDateString(),
            ])
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function activity_proposals_table_filters_status(): void
    {
        $matching = ActivityProposal::factory()->create([
            'status' => ActivityProposalStatus::Accepted,
            'created_by' => $this->admin->id,
        ]);
        $other = ActivityProposal::factory()->create([
            'status' => ActivityProposalStatus::Rejected,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListActivityProposals::class)
            ->filterTable('status', ActivityProposalStatus::Accepted->value)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function activity_type_slots_table_filters_activity_type(): void
    {
        $matchingType = ActivityType::findBySlug(ActivityType::SLUG_RPG) ?? ActivityType::factory()->create();
        $otherType = ActivityType::findBySlug(ActivityType::SLUG_BOARD) ?? ActivityType::factory()->create();
        $matchingSlot = Slot::factory()->create(['created_by' => $this->admin->id]);
        $otherSlot = Slot::factory()->create(['created_by' => $this->admin->id]);

        $matching = ActivityTypeSlot::query()->create([
            'slot_id' => $matchingSlot->id,
            'activity_type_id' => $matchingType->id,
        ]);
        $other = ActivityTypeSlot::query()->create([
            'slot_id' => $otherSlot->id,
            'activity_type_id' => $otherType->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListActivityTypeSlots::class)
            ->filterTable('activityType', $matchingType->id)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function activity_users_table_filters_is_absent(): void
    {
        $matching = ActivityUser::query()->create([
            'activity_id' => Activity::factory()->create(['created_by' => $this->admin->id])->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => true,
            'created_by' => $this->admin->id,
        ]);
        $other = ActivityUser::query()->create([
            'activity_id' => Activity::factory()->create(['created_by' => $this->admin->id])->id,
            'user_id' => User::factory()->create()->id,
            'is_absent' => false,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListActivityUsers::class)
            ->filterTable('is_absent', true)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function event_enrollment_windows_table_filters_starts_at_range(): void
    {
        $matching = EventEnrollmentWindow::factory()->create([
            'starts_at' => now()->addDay(),
            'created_by' => $this->admin->id,
        ]);
        $other = EventEnrollmentWindow::factory()->create([
            'starts_at' => now()->addMonths(2),
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListEventEnrollmentWindows::class)
            ->filterTable('starts_at', [
                'starts_at_from' => now()->toDateString(),
                'starts_at_until' => now()->addDays(7)->toDateString(),
            ])
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }

    #[Test]
    public function events_and_places_and_slots_tables_filter_requested_fields(): void
    {
        $publicEvent = Event::factory()->create([
            'is_public' => true,
            'created_by' => $this->admin->id,
        ]);
        $privateEvent = Event::factory()->create([
            'is_public' => false,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListEvents::class)
            ->filterTable('is_public', true)
            ->assertCanSeeTableRecords([$publicEvent])
            ->assertCanNotSeeTableRecords([$privateEvent]);

        $countryA = Country::factory()->create(['iso_alpha2' => 'PL']);
        $countryB = Country::factory()->create(['iso_alpha2' => 'DE']);
        $venue = Place::factory()->create([
            'type' => Place::TYPE_VENUE,
            'country_id' => $countryA->id,
            'created_by' => $this->admin->id,
        ]);
        $room = Place::factory()->create([
            'type' => Place::TYPE_ROOM,
            'country_id' => $countryB->id,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListPlaces::class)
            ->filterTable('type', Place::TYPE_VENUE)
            ->filterTable('country', $countryA->id)
            ->assertCanSeeTableRecords([$venue])
            ->assertCanNotSeeTableRecords([$room]);

        $matchingSlot = Slot::factory()->create([
            'participation_mode' => ParticipationMode::Lottery,
            'created_by' => $this->admin->id,
        ]);
        $otherSlot = Slot::factory()->create([
            'participation_mode' => ParticipationMode::Open,
            'created_by' => $this->admin->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListSlots::class)
            ->filterTable('participation_mode', ParticipationMode::Lottery->value)
            ->assertCanSeeTableRecords([$matchingSlot])
            ->assertCanNotSeeTableRecords([$otherSlot]);
    }

    #[Test]
    public function tag_related_tables_filter_category_context_and_locale(): void
    {
        $tagCategory = TagCategory::factory()->create();
        $matchingTag = Tag::factory()->create([
            'tag_category_id' => $tagCategory->id,
            'created_by' => $this->admin->id,
        ]);
        $matchingTag->translations()->create([
            'locale' => 'en',
            'label' => 'English label',
            'slug' => 'english-label',
        ]);

        $otherTag = Tag::factory()->create(['created_by' => $this->admin->id]);
        $otherTag->translations()->create([
            'locale' => 'pl',
            'label' => 'Polish label',
            'slug' => 'polish-label',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListTags::class)
            ->filterTable('tagCategory', $tagCategory->id)
            ->filterTable('locale', 'en')
            ->assertCanSeeTableRecords([$matchingTag])
            ->assertCanNotSeeTableRecords([$otherTag]);

        $matchingContext = TagContext::factory()->create([
            'tag_id' => $matchingTag->id,
            'context_type' => ActivityType::class,
            'context_id' => ActivityType::factory()->create()->id,
        ]);
        $otherContext = TagContext::factory()->create([
            'tag_id' => $otherTag->id,
            'context_type' => Event::class,
            'context_id' => Event::factory()->create(['created_by' => $this->admin->id])->id,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListTagContexts::class)
            ->filterTable('context_type', ActivityType::class)
            ->assertCanSeeTableRecords([$matchingContext])
            ->assertCanNotSeeTableRecords([$otherContext]);

        $matchingTranslation = TagTranslation::factory()->create([
            'tag_id' => $matchingTag->id,
            'locale' => 'de',
        ]);
        $otherTranslation = TagTranslation::factory()->create([
            'tag_id' => $otherTag->id,
            'locale' => 'fr',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListTagTranslations::class)
            ->filterTable('locale', 'de')
            ->assertCanSeeTableRecords([$matchingTranslation])
            ->assertCanNotSeeTableRecords([$otherTranslation]);

        $matchingAlias = TagAlias::query()->create([
            'tag_id' => $matchingTag->id,
            'locale' => 'en',
            'alias' => 'matching-alias',
        ]);
        $otherAlias = TagAlias::query()->create([
            'tag_id' => $otherTag->id,
            'locale' => 'pl',
            'alias' => 'other-alias',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListTagAliases::class)
            ->filterTable('locale', 'en')
            ->assertCanSeeTableRecords([$matchingAlias])
            ->assertCanNotSeeTableRecords([$otherAlias]);

        $matchingCategoryTranslation = TagCategoryTranslation::query()->create([
            'tag_category_id' => $tagCategory->id,
            'locale' => 'en',
            'label' => 'Category EN',
        ]);
        $otherCategoryTranslation = TagCategoryTranslation::query()->create([
            'tag_category_id' => TagCategory::factory()->create()->id,
            'locale' => 'pl',
            'label' => 'Category PL',
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListTagCategoryTranslations::class)
            ->filterTable('locale', 'en')
            ->assertCanSeeTableRecords([$matchingCategoryTranslation])
            ->assertCanNotSeeTableRecords([$otherCategoryTranslation]);
    }

    #[Test]
    public function users_table_filters_admin_event_organizer_and_organization(): void
    {
        $org = Organization::factory()->create();
        $matching = User::factory()->create([
            'organization_id' => $org->id,
            'is_admin' => true,
            'is_event_organizer' => true,
        ]);
        $other = User::factory()->create([
            'is_admin' => false,
            'is_event_organizer' => false,
        ]);

        Livewire::actingAs($this->admin)
            ->test(ListUsers::class)
            ->filterTable('is_admin', true)
            ->filterTable('is_event_organizer', true)
            ->filterTable('organization', $org->id)
            ->assertCanSeeTableRecords([$matching])
            ->assertCanNotSeeTableRecords([$other]);
    }
}
