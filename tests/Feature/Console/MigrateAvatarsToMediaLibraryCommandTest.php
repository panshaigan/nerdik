<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class MigrateAvatarsToMediaLibraryCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function test_it_migrates_legacy_avatar_files_to_media_library(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $path = 'avatars/'.$user->id.'.webp';
        $image = UploadedFile::fake()->image('legacy.jpg', 512, 512);
        Storage::disk('public')->put($path, file_get_contents($image->getRealPath()) ?: '');
        $user->profile()->update([
            'avatar_source' => AvatarSource::Uploaded,
            'avatar_path' => $path,
        ]);

        $this->assertSame(0, $user->fresh()->getMedia('avatar')->count());

        Artisan::call('avatars:migrate-to-media-library');

        $user->refresh();
        $this->assertNotNull($user->getFirstMedia('avatar'));
        $this->assertNull($user->profile?->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    #[Test]
    public function test_dry_run_does_not_attach_media(): void
    {
        Storage::fake('public');

        $user = User::factory()->create();
        $path = 'avatars/'.$user->id.'.webp';
        $image = UploadedFile::fake()->image('legacy.jpg', 512, 512);
        Storage::disk('public')->put($path, file_get_contents($image->getRealPath()) ?: '');
        $user->profile()->update([
            'avatar_path' => $path,
        ]);

        Artisan::call('avatars:migrate-to-media-library', ['--dry-run' => true]);

        $this->assertNull($user->fresh()->getFirstMedia('avatar'));
        Storage::disk('public')->assertExists($path);
    }
}
