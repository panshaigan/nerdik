<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Actions\Images\StoreCroppedPublicImage;
use App\Models\Organization;
use Illuminate\Http\UploadedFile;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;

final class StoreUploadedOrganizationLogo
{
    private const int LOGO_SIZE = 512;

    public function __construct(
        private StoreCroppedPublicImage $storeCroppedPublicImage,
    ) {}

    /**
     * Writes `organization-logos/{organization_id}.webp` on the public disk and returns the relative path.
     */
    public function __invoke(Organization $organization, TemporaryUploadedFile|UploadedFile $file): string
    {
        $relativePath = 'organization-logos/'.$organization->id.'.webp';

        return ($this->storeCroppedPublicImage)(
            $relativePath,
            $file,
            self::LOGO_SIZE,
            self::LOGO_SIZE,
        );
    }
}
