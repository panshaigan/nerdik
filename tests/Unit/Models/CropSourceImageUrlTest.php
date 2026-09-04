<?php

declare(strict_types=1);

namespace Tests\Unit\Models;

use App\Actions\Avatars\StoreUploadedAvatar;
use App\Actions\Events\StoreUploadedEventLogo;
use App\Actions\Organizations\StoreUploadedOrganizationLogo;
use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class CropSourceImageUrlTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_returns_null_without_source_media(): void
    {
        $user = User::factory()->create();

        $this->assertNull($user->cropSourceImageUrl());
    }

    #[Test]
    public function user_returns_url_when_source_media_exists(): void
    {
        Storage::fake('public');
        $user = User::factory()->create();

        app(StoreUploadedAvatar::class)(
            $user,
            UploadedFile::fake()->image('avatar.jpg', 512, 512),
            UploadedFile::fake()->image('original.jpg', 1200, 900),
        );

        $url = $user->fresh()->cropSourceImageUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/', $url);
    }

    #[Test]
    public function event_returns_url_when_source_media_exists(): void
    {
        Storage::fake('public');
        $event = Event::factory()->create();

        $this->assertNull($event->cropSourceImageUrl());

        app(StoreUploadedEventLogo::class)(
            $event,
            UploadedFile::fake()->image('logo.jpg', 1280, 720),
            UploadedFile::fake()->image('original.jpg', 2400, 1600),
        );

        $url = $event->fresh()->cropSourceImageUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/', $url);
    }

    #[Test]
    public function organization_returns_url_when_source_file_exists(): void
    {
        Storage::fake('public');
        $organization = Organization::factory()->create();

        $this->assertNull($organization->cropSourceImageUrl());

        app(StoreUploadedOrganizationLogo::class)(
            $organization,
            UploadedFile::fake()->image('logo.jpg', 512, 512),
            UploadedFile::fake()->image('original.jpg', 1200, 900),
        );

        $url = $organization->fresh()->cropSourceImageUrl();
        $this->assertNotNull($url);
        $this->assertStringContainsString('/storage/organization-logos/'.$organization->id.'-source.webp', $url);
    }
}
