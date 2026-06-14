<?php

namespace Tests\Feature\Livewire;

use App\Enums\EventLogoSource;
use App\Livewire\Events\ManageEventForm;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ManageEventFormUiTest extends TestCase
{
    use RefreshDatabase;

    public function test_save_switches_to_main_details_tab_when_name_missing_from_another_tab(): void
    {
        $user = User::factory()->organizer()->create();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->assertSeeHtml('novalidate')
            ->set('tab', 'image')
            ->call('save')
            ->assertSet('tab', 'main-details')
            ->assertHasErrors(['name'])
            ->assertSeeHtml('data-ui="form-errors"');
    }

    public function test_save_switches_to_image_tab_when_default_logo_selection_missing(): void
    {
        $user = User::factory()->organizer()->create();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('tab', 'main-details')
            ->set('name', 'Gallery Logo Event')
            ->set('starts_at', now()->addDays(7)->format('Y-m-d\TH:i'))
            ->set('ends_at', now()->addDays(8)->format('Y-m-d\TH:i'))
            ->set('enrollment_windows.0.name', 'Window 1')
            ->set('logo_source', EventLogoSource::Default->value)
            ->call('save')
            ->assertSet('tab', 'image')
            ->assertHasErrors(['listing_media_id']);
    }

    public function test_save_switches_to_first_tab_with_errors_when_multiple_tabs_fail(): void
    {
        $user = User::factory()->organizer()->create();

        Livewire::actingAs($user)
            ->test(ManageEventForm::class)
            ->set('tab', 'image')
            ->set('logo_source', EventLogoSource::Default->value)
            ->call('save')
            ->assertSet('tab', 'main-details')
            ->assertHasErrors(['name', 'starts_at', 'ends_at', 'listing_media_id']);
    }
}
