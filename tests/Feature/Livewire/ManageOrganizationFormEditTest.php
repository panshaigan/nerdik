<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Enums\OrganizationLogoSource;
use App\Livewire\Organizations\ManageOrganizationForm;
use App\Livewire\Organizations\OrganizationIndex;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class ManageOrganizationFormEditTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function organization_list_shows_edit_icon_for_owner(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $user->id,
            'name' => 'Editable Listed Org',
        ]);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->assertSeeHtml('data-ui="organization-index-edit"')
            ->assertSee(route('organizations.edit', $organization), false);
    }

    #[Test]
    public function organization_list_hides_edit_icon_for_non_owner_admin_bypass_only_via_can_modify(): void
    {
        $owner = User::factory()->create(['is_event_organizer' => true]);
        $other = User::factory()->create(['is_event_organizer' => true]);
        Organization::factory()->create([
            'created_by' => $owner->id,
            'name' => 'Someone Else Org',
        ]);

        Livewire::actingAs($other)
            ->test(OrganizationIndex::class)
            ->assertDontSeeHtml('data-ui="organization-index-edit"')
            ->assertSee(__('ui.organizations.empty'));
    }

    #[Test]
    public function edit_form_keeps_submit_button_inside_organization_form(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $user->id,
            'name' => 'Nested Form Org',
            'logo_source' => OrganizationLogoSource::Generated,
        ]);

        $html = Livewire::actingAs($user)
            ->test(ManageOrganizationForm::class, ['organization' => $organization])
            ->html();

        $this->assertMatchesRegularExpression(
            '/<form\b[^>]*\bdata-org-form\b[^>]*>.*\bdata-ui="organization-submit".*<\/form>/s',
            $html,
        );
        $this->assertStringContainsString('data-ui="organization-form-entity-links"', $html);
        $this->assertStringContainsString('data-ui="entity-links-modals"', $html);
        $this->assertStringContainsString('x-teleport="body"', $html);
    }

    #[Test]
    public function owner_can_update_organization_from_edit_form(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $user->id,
            'name' => 'Before Update Org',
            'acronym' => 'BUO',
            'logo_source' => OrganizationLogoSource::Generated,
            'logo_bg_color' => '#111111',
            'logo_text_color' => '#eeeeee',
        ]);

        Livewire::actingAs($user)
            ->test(ManageOrganizationForm::class, ['organization' => $organization])
            ->set('name', 'After Update Org')
            ->set('acronym', 'AUO')
            ->set('description', '<p>Updated description</p>')
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('organizations.index'));

        $organization->refresh();
        $this->assertSame('After Update Org', $organization->name);
        $this->assertSame('AUO', $organization->acronym);
    }
}
