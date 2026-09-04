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

final class OrganizationIndexLogoTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function save_persists_generated_logo_settings(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->set('modalOpen', true)
            ->set('modalMode', 'create')
            ->set('name', 'Generated Logo Org')
            ->set('acronym', 'GLO')
            ->set('logo_source', OrganizationLogoSource::Generated->value)
            ->set('logo_bg_color', '#ff0000')
            ->set('logo_text_color', '#00ff00')
            ->call('save')
            ->assertHasNoErrors();

        $organization = Organization::query()->where('name', 'Generated Logo Org')->first();
        $this->assertNotNull($organization);
        $this->assertSame(OrganizationLogoSource::Generated, $organization->logo_source);
        $this->assertSame('GLO', $organization->acronym);
        $this->assertSame('#ff0000', $organization->logo_bg_color);
        $this->assertSame('#00ff00', $organization->logo_text_color);
        $this->assertNull($organization->logo_path);
        $this->assertStringContainsString('ui-avatars.com/api/', $organization->logoUrl());
        $this->assertStringContainsString('name=GLO', $organization->logoUrl());
    }

    #[Test]
    public function save_persists_uploaded_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['is_event_organizer' => true]);
        $file = UploadedFile::fake()->image('logo.jpg', 640, 480);
        $source = UploadedFile::fake()->image('original.jpg', 1200, 900);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->set('modalOpen', true)
            ->set('modalMode', 'create')
            ->set('name', 'Uploaded Logo Org')
            ->set('logo_source', OrganizationLogoSource::Upload->value)
            ->set('croppedLogo', $file)
            ->set('sourceImage', $source)
            ->call('save')
            ->assertHasNoErrors();

        $organization = Organization::query()->where('name', 'Uploaded Logo Org')->first();
        $this->assertNotNull($organization);
        $this->assertSame(OrganizationLogoSource::Upload, $organization->logo_source);
        $this->assertSame('organization-logos/'.$organization->id.'.webp', $organization->logo_path);
        Storage::disk('public')->assertExists('organization-logos/'.$organization->id.'.webp');
        Storage::disk('public')->assertExists('organization-logos/'.$organization->id.'-source.webp');
        $this->assertStringContainsString('/storage/organization-logos/'.$organization->id.'.webp', $organization->logoUrl());
    }

    #[Test]
    public function recrop_without_new_source_preserves_existing_source(): void
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

        $sourcePath = 'organization-logos/'.$organization->id.'-source.webp';
        Storage::disk('public')->assertExists($sourcePath);
        $originalSourceContents = Storage::disk('public')->get($sourcePath);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->call('openEditModal', $organization->id)
            ->set('logo_source', OrganizationLogoSource::Upload->value)
            ->set('croppedLogo', UploadedFile::fake()->image('recrop.jpg', 512, 512))
            ->call('save')
            ->assertHasNoErrors();

        Storage::disk('public')->assertExists($sourcePath);
        $this->assertSame($originalSourceContents, Storage::disk('public')->get($sourcePath));
        $this->assertNotNull($organization->fresh()->cropSourceImageUrl());
    }

    #[Test]
    public function switching_to_generated_deletes_uploaded_logo_file(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $user->id,
            'logo_source' => OrganizationLogoSource::Upload,
            'logo_path' => 'organization-logos/legacy.webp',
        ]);
        Storage::disk('public')->put('organization-logos/legacy.webp', 'logo');

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->call('openEditModal', $organization->id)
            ->set('logo_source', OrganizationLogoSource::Generated->value)
            ->call('save')
            ->assertHasNoErrors();

        $organization->refresh();
        $this->assertSame(OrganizationLogoSource::Generated, $organization->logo_source);
        $this->assertNull($organization->logo_path);
        Storage::disk('public')->assertMissing('organization-logos/legacy.webp');
    }

    #[Test]
    public function validation_requires_crop_when_upload_selected_on_create(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->set('modalOpen', true)
            ->set('modalMode', 'create')
            ->set('name', 'Missing Crop Org')
            ->set('logo_source', OrganizationLogoSource::Upload->value)
            ->call('save')
            ->assertHasErrors(['croppedLogo' => 'required']);
    }

    #[Test]
    public function clear_cropped_logo_resets_pending_upload(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);
        $file = UploadedFile::fake()->image('logo.jpg', 64, 64);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->set('logo_source', OrganizationLogoSource::Upload->value)
            ->set('croppedLogo', $file)
            ->call('clearCroppedLogo')
            ->assertSet('croppedLogo', null);
    }

    #[Test]
    public function save_persists_acronym_with_uploaded_logo(): void
    {
        Storage::fake('public');
        $user = User::factory()->create(['is_event_organizer' => true]);
        $file = UploadedFile::fake()->image('logo.jpg', 640, 480);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->set('modalOpen', true)
            ->set('modalMode', 'create')
            ->set('name', 'Uploaded With Acronym Org')
            ->set('acronym', 'uwa')
            ->set('logo_source', OrganizationLogoSource::Upload->value)
            ->set('croppedLogo', $file)
            ->call('save')
            ->assertHasNoErrors();

        $organization = Organization::query()->where('name', 'Uploaded With Acronym Org')->first();
        $this->assertNotNull($organization);
        $this->assertSame('UWA', $organization->acronym);
    }

    #[Test]
    public function validation_rejects_acronym_longer_than_five_characters(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->set('modalOpen', true)
            ->set('modalMode', 'create')
            ->set('name', 'Long Acronym Org')
            ->set('acronym', 'TOOLONG')
            ->set('logo_source', OrganizationLogoSource::Generated->value)
            ->call('save')
            ->assertHasErrors(['acronym' => 'max']);
    }

    #[Test]
    public function organization_list_renders_logo_thumbnail(): void
    {
        $user = User::factory()->create(['is_event_organizer' => true]);
        $organization = Organization::factory()->create([
            'created_by' => $user->id,
            'name' => 'Listed Org',
            'acronym' => 'LO',
            'logo_bg_color' => '#111111',
            'logo_text_color' => '#eeeeee',
        ]);

        Livewire::actingAs($user)
            ->test(OrganizationIndex::class)
            ->assertSeeHtml('alt="Listed Org"')
            ->assertSeeHtml('ui-avatars.com/api/')
            ->assertSeeHtml('name=LO');
    }
}
