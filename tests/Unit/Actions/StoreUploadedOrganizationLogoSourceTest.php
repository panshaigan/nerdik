<?php

declare(strict_types=1);

namespace Tests\Unit\Actions;

use App\Actions\Organizations\DeleteUploadedOrganizationLogo;
use App\Actions\Organizations\StoreUploadedOrganizationLogo;
use App\Enums\OrganizationLogoSource;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class StoreUploadedOrganizationLogoSourceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_stores_cropped_logo_and_source_webp(): void
    {
        Storage::fake('public');

        $user = User::factory()->organizer()->create();
        $organization = Organization::factory()->create([
            'created_by' => $user->id,
            'logo_source' => OrganizationLogoSource::Upload,
        ]);

        $path = app(StoreUploadedOrganizationLogo::class)(
            $organization,
            UploadedFile::fake()->image('crop.jpg', 512, 512),
            UploadedFile::fake()->image('original.jpg', 1200, 1200),
        );

        $this->assertSame('organization-logos/'.$organization->id.'.webp', $path);
        Storage::disk('public')->assertExists($path);
        Storage::disk('public')->assertExists('organization-logos/'.$organization->id.'-source.webp');
    }

    #[Test]
    public function delete_removes_cropped_and_source_files(): void
    {
        Storage::fake('public');

        $user = User::factory()->organizer()->create();
        $organization = Organization::factory()->create([
            'created_by' => $user->id,
            'logo_source' => OrganizationLogoSource::Upload,
        ]);

        $path = app(StoreUploadedOrganizationLogo::class)(
            $organization,
            UploadedFile::fake()->image('crop.jpg', 512, 512),
            UploadedFile::fake()->image('original.jpg', 800, 800),
        );
        $organization->update(['logo_path' => $path]);

        app(DeleteUploadedOrganizationLogo::class)($organization->fresh());

        Storage::disk('public')->assertMissing($path);
        Storage::disk('public')->assertMissing('organization-logos/'.$organization->id.'-source.webp');
    }
}
