<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Models\Organization;
use Illuminate\Support\Facades\Storage;

final class DeleteUploadedOrganizationLogo
{
    public function __invoke(Organization $organization): void
    {
        if (filled($organization->logo_path)) {
            Storage::disk('public')->delete((string) $organization->logo_path);
        }

        $canonical = 'organization-logos/'.$organization->id.'.webp';
        if (Storage::disk('public')->exists($canonical)) {
            Storage::disk('public')->delete($canonical);
        }
    }
}
