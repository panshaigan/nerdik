<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Avatars\StoreUploadedAvatar;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\Support\AssertsResponsiveMedia;
use Tests\TestCase;

final class StoreUploadedAvatarSourceTest extends TestCase
{
    use AssertsResponsiveMedia;
    use RefreshDatabase;

    #[Test]
    public function it_stores_cropped_avatar_and_source_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();

        app(StoreUploadedAvatar::class)(
            $user,
            UploadedFile::fake()->image('crop.jpg', 512, 512),
            UploadedFile::fake()->image('original.jpg', 640, 360),
        );

        $user->refresh();
        $avatar = $user->getFirstMedia('avatar');
        $source = $user->getFirstMedia('source');

        $this->assertNotNull($avatar);
        $this->assertAvatarMediaIsReady($avatar);
        $this->assertNotNull($source);
        $this->assertSame('image/webp', $source->mime_type);
    }
}
