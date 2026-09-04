<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Actions\Organizations\StoreUploadedOrganizationLogo;
use App\Enums\OrganizationLogoSource;
use App\Livewire\Organizations\OrganizationIndex;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class OrganizationLogoCropModalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function organization_index_renders_crop_modal_outside_org_form(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->set('modalOpen', true)
            ->set('logo_source', 'upload')
            ->assertSeeHtml('id="ui-image-crop-modal"')
            ->assertSeeHtml('class="modal backdrop-blur z-[100030]"')
            ->assertSeeHtml('data-image-crop-stage')
            ->assertSeeHtml('data-image-crop-zoom')
            ->assertSeeHtml('data-org-modal-form');
    }

    #[Test]
    public function org_form_has_crop_upload_hooks_and_without_trap_focus_modal(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);

        $component = Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->set('modalOpen', true)
            ->set('logo_source', 'upload');

        $html = $component->html();

        $this->assertStringContainsString('data-image-crop-dropzone', $html);
        $this->assertStringContainsString('data-image-crop-source-wire-property="sourceImage"', $html);
        $this->assertStringContainsString('data-image-crop-file-trigger', $html);
        $this->assertStringContainsString('data-image-crop-recrop-saved', $html);
        $this->assertStringContainsString('data-org-modal-form', $html);
        $this->assertStringContainsString('data-org-modal', $html);
        $this->assertStringNotContainsString('x-trap="open"', $html);
        $this->assertStringContainsString('ui-org-logo-file', $html);
        $this->assertStringNotContainsString('data-image-crop-source-url=', $html);

        if (preg_match('/data-org-modal-form[\s\S]*?<\/form>/', $html, $matches) === 1) {
            $this->assertStringNotContainsString('id="ui-image-crop-modal"', $matches[0]);
        } else {
            $this->fail('Organization modal form markup was not rendered.');
        }

        $this->assertStringContainsString('id="ui-image-crop-modal"', $html);
    }

    #[Test]
    public function org_edit_form_exposes_saved_source_url_for_recrop(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $user->id,
            'logo_source' => OrganizationLogoSource::Upload,
        ]);

        app(StoreUploadedOrganizationLogo::class)(
            $organization,
            UploadedFile::fake()->image('logo.jpg', 640, 480),
            UploadedFile::fake()->image('original.jpg', 1200, 900),
        );
        $organization->logo_path = 'organization-logos/'.$organization->id.'.webp';
        $organization->save();

        $sourceUrl = $organization->fresh()->cropSourceImageUrl();
        $this->assertNotNull($sourceUrl);

        $html = Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->call('openEditModal', $organization->id)
            ->set('logo_source', OrganizationLogoSource::Upload->value)
            ->html();

        $this->assertStringContainsString('data-image-crop-source-url="'.e($sourceUrl).'"', $html);
        $this->assertStringContainsString('data-image-crop-recrop-saved', $html);
    }
}
