<?php

declare(strict_types=1);

namespace App\Actions\Activities;

use App\Models\Activity;
use Illuminate\Support\Facades\Storage;

final class DeleteUploadedActivityLogo
{
    public function __invoke(Activity $activity): void
    {
        $activity->clearMediaCollection('logo');

        if (filled($activity->logo_path)) {
            Storage::disk('public')->delete((string) $activity->logo_path);
        }

        $canonical = 'activity-logos/'.$activity->id.'.webp';
        if (Storage::disk('public')->exists($canonical)) {
            Storage::disk('public')->delete($canonical);
        }
    }
}
