<?php

declare(strict_types=1);

namespace App\Actions\Events;

use App\Models\Event;
use Illuminate\Support\Facades\Storage;

final class DeleteUploadedEventLogo
{
    public function __invoke(Event $event): void
    {
        $event->clearMediaCollection('logo');

        if (filled($event->logo_path)) {
            Storage::disk('public')->delete((string) $event->logo_path);
        }

        $canonical = 'event-logos/'.$event->id.'.webp';
        if (Storage::disk('public')->exists($canonical)) {
            Storage::disk('public')->delete($canonical);
        }
    }
}
