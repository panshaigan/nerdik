<?php

declare(strict_types=1);

namespace App\Actions\Organizations;

use App\Enums\OrganizationLogoSource;
use App\Models\Organization;
use Illuminate\Support\Facades\Storage;

final class ResolveOrganizationLogoUrl
{
    public function __invoke(Organization $organization, bool $forBadge = false): string
    {
        $generated = $organization->generatedLogoUrl();

        $rawSource = $organization->logo_source;
        $source = $rawSource instanceof OrganizationLogoSource
            ? $rawSource
            : (OrganizationLogoSource::tryFrom((string) ($rawSource ?? OrganizationLogoSource::Generated->value)) ?? OrganizationLogoSource::Generated);

        if ($source === OrganizationLogoSource::Generated) {
            return $generated;
        }

        if ($forBadge) {
            $badgePath = StoreUploadedOrganizationLogo::badgeRelativePath($organization);
            if (Storage::disk('public')->exists($badgePath)) {
                return Storage::disk('public')->url($badgePath);
            }
        }

        $path = $organization->logo_path;
        if (is_string($path) && $path !== '' && Storage::disk('public')->exists($path)) {
            return Storage::disk('public')->url($path);
        }

        return $generated;
    }
}
