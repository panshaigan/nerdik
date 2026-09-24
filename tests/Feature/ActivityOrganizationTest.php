<?php

namespace Tests\Feature;

use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Organization;
use App\Models\User;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ActivityOrganizationTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function manage_form_attaches_existing_organization_by_id(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Attached Guild']);
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Org Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('organization_id', $organization->id)
            ->set('organization_name', $organization->name)
            ->call('save')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('activities', [
            'name' => 'Org Activity',
            'organization_id' => $organization->id,
        ]);
    }

    #[Test]
    public function manage_form_reuses_existing_organization_case_insensitively(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $organization = Organization::factory()->create(['name' => 'Alpha Guild']);
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Case Org Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('organization_id', null)
            ->set('organization_name', 'alpha guild')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertSame(1, Organization::query()->whereRaw('LOWER(name) = LOWER(?)', ['alpha guild'])->count());
        $this->assertDatabaseHas('activities', [
            'name' => 'Case Org Activity',
            'organization_id' => $organization->id,
        ]);
    }

    #[Test]
    public function manage_form_creates_organization_from_name(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'New Org Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('organization_id', null)
            ->set('organization_name', 'Brand New Guild')
            ->call('save')
            ->assertHasNoErrors();

        $organization = Organization::query()->where('name', 'Brand New Guild')->first();
        $this->assertNotNull($organization);
        $this->assertDatabaseHas('activities', [
            'name' => 'New Org Activity',
            'organization_id' => $organization->id,
        ]);
    }

    #[Test]
    public function manage_form_clears_organization_when_name_empty(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'organization_id' => $organization->id,
            'hosting_mode' => Activity::HOSTING_MODE_DRAFT,
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->assertSet('organization_id', $organization->id)
            ->set('organization_id', null)
            ->set('organization_name', '')
            ->call('save')
            ->assertHasNoErrors();

        $this->assertNull($activity->refresh()->organization_id);
    }

    #[Test]
    public function manage_form_renders_organization_field(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->assertSeeHtml('data-activity-org-input')
            ->assertSeeHtml('data-activity-org-id');
    }
}
