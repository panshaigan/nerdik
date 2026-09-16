<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ManageActivityFormActivityTypesTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function create_form_allows_non_rpg_activity_types(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $boardTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_BOARD)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Catan Night')
            ->set('activity_type_id', $boardTypeId)
            ->set('min_participants', 2)
            ->set('max_participants', 4)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect();

        $this->assertDatabaseHas('activities', [
            'name' => 'Catan Night',
            'activity_type_id' => $boardTypeId,
            'max_participants' => 4,
        ]);
    }

    #[Test]
    public function changing_activity_type_clamps_participants_to_type_limit(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $lectureTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_LECTURE)?->id;
        $boardTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_BOARD)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('activity_type_id', $lectureTypeId)
            ->set('min_participants', 10)
            ->set('max_participants', 80)
            ->assertSet('max_participants', 80)
            ->set('activity_type_id', $boardTypeId)
            ->assertSet('max_participants', 8)
            ->assertSet('min_participants', 8);
    }

    #[Test]
    public function save_rejects_participants_above_activity_type_limit(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $boardTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_BOARD)?->id;

        ActivityType::query()->whereKey($boardTypeId)->update(['max_participants_limit' => 6]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Too Many Players')
            ->set('activity_type_id', $boardTypeId)
            ->set('min_participants', 2)
            ->set('max_participants', 7)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->call('save')
            ->assertHasErrors(['max_participants']);
    }

    #[Test]
    public function participants_max_limit_comes_from_selected_activity_type(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $lectureTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_LECTURE)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('activity_type_id', $lectureTypeId)
            ->assertSet('activity_type_id', $lectureTypeId)
            ->assertViewHas('participantsMaxLimit', 100);
    }
}
