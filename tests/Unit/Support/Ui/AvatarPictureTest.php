<?php

declare(strict_types=1);

namespace Tests\Unit\Support\Ui;

use App\Actions\Avatars\AttachUserAvatarFromPath;
use App\Models\User;
use App\Support\Ui\AvatarPicture;
use App\Support\Ui\AvatarSlot;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\MediaLibrary\Conversions\Jobs\PerformConversionsJob;
use Tests\Support\AssertsResponsiveMedia;
use Tests\TestCase;

final class AvatarPictureTest extends TestCase
{
    use AssertsResponsiveMedia;
    use RefreshDatabase;

    #[Test]
    public function it_returns_original_media_url_while_conversions_are_pending(): void
    {
        Queue::fake();
        config([
            'media.queue_conversions' => true,
            'media-library.queue_conversions_by_default' => true,
            'media-library.queue_connection_name' => 'database',
        ]);

        Storage::fake('public');
        $user = User::factory()->create();
        $user->profile()->update([
            'avatar_bg_color' => '#112233',
            'avatar_text_color' => '#ddeeff',
            'avatar_initials' => 'AB',
        ]);

        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/test.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        $user->addMedia($tempPath)
            ->withCustomProperties(['width' => 512, 'height' => 512])
            ->toMediaCollection('avatar');

        Queue::assertPushed(PerformConversionsJob::class);
        $this->assertTrue($user->fresh()->avatarConversionsPending());

        $picture = AvatarPicture::fromUser($user->fresh(), AvatarSlot::Badge);

        $this->assertTrue($picture->hasDisplayableImage());
        $this->assertFalse($picture->isPendingPlaceholder);
        $this->assertStringNotContainsString('ui-avatars.com', (string) $picture->url);
        $this->assertSame($user->fresh()->pendingAvatarOriginalUrl(), $picture->url);
    }

    #[Test]
    public function it_returns_fixed_conversion_url_when_conversions_are_ready(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        $file = UploadedFile::fake()->image('avatar.jpg', 512, 512);
        $tempPath = Storage::disk('public')->path('media/temp/avatars/test-ready.webp');
        Storage::disk('public')->makeDirectory('media/temp/avatars');
        copy($file->getRealPath(), $tempPath);

        app(AttachUserAvatarFromPath::class)($user, $tempPath);

        $media = $user->fresh()->getFirstMedia('avatar');
        $this->assertNotNull($media);
        $this->assertAvatarMediaIsReady($media);

        $badgePicture = AvatarPicture::fromUser($user->fresh(), AvatarSlot::Badge);
        $heroPicture = AvatarPicture::fromUser($user->fresh(), AvatarSlot::Hero);
        $previewPicture = AvatarPicture::fromUser($user->fresh(), AvatarSlot::Preview);

        $this->assertTrue($badgePicture->hasDisplayableImage());
        $this->assertStringContainsString('avatar_32', (string) $badgePicture->url);
        $this->assertStringContainsString('avatar_118', (string) $heroPicture->url);
        $this->assertStringContainsString('avatar_512', (string) $previewPicture->url);
    }

    #[Test]
    public function it_maps_slots_to_conversion_names(): void
    {
        $this->assertSame('avatar_32', AvatarSlot::Badge->conversionName());
        $this->assertSame('avatar_118', AvatarSlot::Hero->conversionName());
        $this->assertSame('avatar_512', AvatarSlot::Preview->conversionName());
    }
}
