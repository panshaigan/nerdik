<?php

namespace Tests\Feature\Livewire;

use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageActivityFormUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_edit_form_does_not_render_name_suggestions_popup(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->assertDontSeeHtml('data-activity-name-popup')
            ->assertDontSeeHtml('activity-name-suggestions-popup');
    }

    public function test_edit_form_clamps_legacy_min_participants_below_one(): void
    {
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'min_participants' => 0,
            'max_participants' => 6,
        ]);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->assertSet('min_participants', 1)
            ->assertSet('max_participants', 6);
    }
}
