<?php

declare(strict_types=1);

namespace Tests\Feature\Livewire;

use App\Actions\Activities\StoreUploadedActivityLogo;
use App\Actions\Media\StoreUserGalleryImage;
use App\Enums\ActivityLogoSource;
use App\Livewire\Activities\ManageActivityForm;
use App\Models\Activity;
use App\Models\ActivityType;
use App\Models\Tag;
use App\Models\User;
use App\Support\Media\UserGalleryCatalog;
use Database\Seeders\ActivityTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\MediaCollections\Models\Media;
use Tests\Support\AttachesFixtureMedia;
use Tests\TestCase;

final class ManageActivityFormImageTest extends TestCase
{
    use AttachesFixtureMedia;
    use RefreshDatabase;

    #[Test]
    public function image_tab_lists_media_for_selected_tags(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        [$tag, $media] = $this->createTagWithImage('Warhammer');

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('tab', 'image')
            ->set('tag_ids', [(int) $tag->id])
            ->set('logo_source', ActivityLogoSource::Tag->value)
            ->assertSee('Warhammer')
            ->assertSeeHtml('@click="selectedMediaId = '.$media->id.'"');
    }

    #[Test]
    public function save_persists_tag_image_selection(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        [$tag, $media] = $this->createTagWithImage('Dungeons');
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Image Test Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('tag_ids', [(int) $tag->id])
            ->set('logo_source', ActivityLogoSource::Tag->value)
            ->set('selected_tag_media_id', (int) $media->id)
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::query()->where('name', 'Image Test Activity')->first();
        $this->assertNotNull($activity);
        $this->assertSame(ActivityLogoSource::Tag, $activity->logo_source);
        $this->assertSame((int) $media->id, (int) $activity->tag_media_id);
    }

    #[Test]
    public function removing_tag_clears_invalid_media_selection(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        [$tag, $media] = $this->createTagWithImage('Pathfinder');
        $otherTag = Tag::factory()->create();

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('tag_ids', [(int) $tag->id, (int) $otherTag->id])
            ->set('logo_source', ActivityLogoSource::Tag->value)
            ->set('selected_tag_media_id', (int) $media->id)
            ->set('tag_ids', [(int) $otherTag->id])
            ->assertSet('selected_tag_media_id', null);
    }

    #[Test]
    public function validation_rejects_media_not_on_selected_tags(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        [, $media] = $this->createTagWithImage('Shadowrun');
        $otherTag = Tag::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Invalid Image Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('tag_ids', [(int) $otherTag->id])
            ->set('logo_source', ActivityLogoSource::Tag->value)
            ->set('selected_tag_media_id', (int) $media->id)
            ->call('save')
            ->assertHasErrors(['selected_tag_media_id']);
    }

    #[Test]
    public function save_persists_uploaded_logo(): void
    {
        Storage::fake('public');
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;
        $file = UploadedFile::fake()->image('cover.jpg', 800, 450);
        $source = UploadedFile::fake()->image('original.jpg', 640, 360);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Upload Logo Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('logo_source', ActivityLogoSource::Upload->value)
            ->set('croppedLogo', $file)
            ->set('sourceImage', $source)
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::query()->where('name', 'Upload Logo Activity')->first();
        $this->assertNotNull($activity);
        $this->assertSame(ActivityLogoSource::Gallery, $activity->logo_source);
        $this->assertNull($activity->logo_path);
        $this->assertNotNull($activity->gallery_media_id);
        $this->assertNull($activity->getFirstMedia('logo'));
        $this->assertCount(1, $user->fresh()->getMedia('gallery'));
        $galleryMedia = $user->fresh()->getMedia('gallery')->first();
        $this->assertNotNull($galleryMedia?->getCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY));
    }

    #[Test]
    public function validation_requires_crop_when_upload_selected_on_create(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Missing Logo Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('logo_source', ActivityLogoSource::Upload->value)
            ->call('save')
            ->assertHasErrors(['croppedLogo']);
    }

    #[Test]
    public function edit_keeps_existing_logo_without_new_crop(): void
    {
        Storage::fake('public');
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => ActivityLogoSource::Upload,
            'logo_path' => null,
            'tag_media_id' => null,
        ]);
        app(StoreUploadedActivityLogo::class)(
            $activity,
            UploadedFile::fake()->image('existing.jpg', 800, 450),
        );
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->set('name', 'Updated Name Only')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('logo_source', ActivityLogoSource::Upload->value)
            ->call('save')
            ->assertHasNoErrors();

        $activity->refresh();
        $this->assertNotNull($activity->getFirstMedia('logo'));
        $this->assertNull($activity->logo_path);
        $this->assertSame('Updated Name Only', $activity->name);
    }

    #[Test]
    public function recrop_without_new_source_preserves_existing_source(): void
    {
        Storage::fake('public');
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => ActivityLogoSource::Upload,
            'logo_path' => null,
            'tag_media_id' => null,
        ]);
        app(StoreUploadedActivityLogo::class)(
            $activity,
            UploadedFile::fake()->image('existing.jpg', 800, 450),
            UploadedFile::fake()->image('original.jpg', 640, 360),
        );

        $sourceUrl = $activity->fresh()->cropSourceImageUrl();
        $this->assertNotNull($sourceUrl);
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        $html = Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->set('tab', 'image')
            ->set('logo_source', ActivityLogoSource::Upload->value)
            ->html();

        $this->assertStringContainsString('data-image-crop-source-url="'.e($sourceUrl).'"', $html);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('logo_source', ActivityLogoSource::Upload->value)
            ->set('croppedLogo', UploadedFile::fake()->image('recrop.jpg', 800, 450))
            ->call('save')
            ->assertHasNoErrors();

        $activity->refresh();
        $this->assertSame(ActivityLogoSource::Gallery, $activity->logo_source);
        $this->assertNotNull($activity->gallery_media_id);
        $this->assertNull($activity->getFirstMedia('logo'));
        $this->assertCount(1, $user->fresh()->getMedia('gallery'));
    }

    #[Test]
    public function switching_logo_source_from_upload_to_tag_deletes_stored_file(): void
    {
        Storage::fake('public');
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        [$tag, $media] = $this->createTagWithImage('Switch Source');
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => ActivityLogoSource::Upload,
            'logo_path' => null,
        ]);
        app(StoreUploadedActivityLogo::class)(
            $activity,
            UploadedFile::fake()->image('logo.jpg', 800, 450),
        );
        $activity->tags()->attach($tag->id);
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('logo_source', ActivityLogoSource::Tag->value)
            ->set('selected_tag_media_id', (int) $media->id)
            ->call('save')
            ->assertHasNoErrors();

        $activity->refresh();
        $this->assertSame(ActivityLogoSource::Tag, $activity->logo_source);
        $this->assertNull($activity->logo_path);
        $this->assertNull($activity->getFirstMedia('logo'));
    }

    #[Test]
    public function edit_form_loads_existing_tag_image_selection(): void
    {
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        [$tag, $media] = $this->createTagWithImage('Call of Cthulhu');
        $activity = Activity::factory()->create([
            'created_by' => $user->id,
            'updated_by' => $user->id,
            'logo_source' => ActivityLogoSource::Tag,
            'tag_media_id' => $media->id,
        ]);
        $activity->tags()->attach($tag->id);

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class, ['activity' => $activity])
            ->assertSet('logo_source', ActivityLogoSource::Tag->value)
            ->assertSet('selected_tag_media_id', (int) $media->id);
    }

    #[Test]
    public function gallery_selection_with_crop_stores_entity_logo_without_mutating_gallery(): void
    {
        Storage::fake('public');
        $this->seed(ActivityTypeSeeder::class);
        $user = User::factory()->create();
        $activityTypeId = (int) ActivityType::findBySlug(ActivityType::SLUG_RPG)?->id;
        $media = app(StoreUserGalleryImage::class)(
            $user,
            UploadedFile::fake()->image('gallery.jpg', 800, 450),
            1280,
            720,
            UploadedFile::fake()->image('original.jpg', 640, 360),
        );
        $media->refresh();
        $gallerySourcePath = $media->getCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY);
        $galleryFileName = $media->file_name;

        Livewire::actingAs($user)
            ->test(ManageActivityForm::class)
            ->set('name', 'Gallery Crop Activity')
            ->set('activity_type_id', $activityTypeId)
            ->set('hosting_mode', Activity::HOSTING_MODE_DRAFT)
            ->set('logo_source', ActivityLogoSource::Gallery->value)
            ->set('gallery_media_id', (int) $media->id)
            ->set('croppedLogo', UploadedFile::fake()->image('crop.jpg', 800, 450))
            ->call('save')
            ->assertHasNoErrors();

        $activity = Activity::query()->where('name', 'Gallery Crop Activity')->first();
        $this->assertNotNull($activity);
        $this->assertSame(ActivityLogoSource::Gallery, $activity->logo_source);
        $this->assertSame((int) $media->id, (int) $activity->gallery_media_id);
        $this->assertNotNull($activity->getFirstMedia('logo'));

        $media->refresh();
        $this->assertSame($galleryFileName, $media->file_name);
        $this->assertSame($gallerySourcePath, $media->getCustomProperty(UserGalleryCatalog::SOURCE_PATH_PROPERTY));
    }

    /**
     * @return array{0: Tag, 1: Media}
     */
    private function createTagWithImage(string $label): array
    {
        $tag = Tag::factory()->create();
        $tag->translations()->create([
            'locale' => 'en',
            'label' => $label,
        ]);

        $media = $this->attachTagSampleMedia($tag, 'tests/fixtures/manage-activity-'.$label.'.jpg');

        return [$tag, $media];
    }
}
