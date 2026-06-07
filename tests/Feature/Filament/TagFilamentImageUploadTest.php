<?php

declare(strict_types=1);

namespace Tests\Feature\Filament;

use App\Filament\Admin\Resources\Tags\Pages\EditTag;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsResponsiveMedia;
use Tests\TestCase;

final class TagFilamentImageUploadTest extends TestCase
{
    use AssertsResponsiveMedia;
    use RefreshDatabase;

    #[Test]
    public function admin_can_upload_tag_images_with_responsive_conversions(): void
    {
        config(['media.test_profile' => 'full']);

        Storage::fake('public');

        $admin = User::factory()->admin()->create();
        $tag = Tag::factory()->create();

        Livewire::actingAs($admin)
            ->test(EditTag::class, ['record' => $tag->id])
            ->fillForm([
                'images' => [
                    UploadedFile::fake()->image('tag-upload.jpg', 800, 600),
                ],
            ])
            ->call('save')
            ->assertHasNoFormErrors();

        $media = $tag->refresh()->getFirstMedia('images');

        $this->assertNotNull($media);
        $this->assertNotNull($media->getCustomProperty('width'));
        $this->assertNotNull($media->getCustomProperty('height'));
        $this->assertMediaHasResponsiveConversions($media);
    }
}
