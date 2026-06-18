<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\UserAvatarReady;
use App\Models\User;
use Spatie\MediaLibrary\Conversions\Events\ConversionHasBeenCompletedEvent;

final class NotifyUserAvatarReady
{
    public function handle(ConversionHasBeenCompletedEvent $event): void
    {
        $media = $event->media;

        if ($media->collection_name !== 'avatar') {
            return;
        }

        $model = $media->model;

        if (! $model instanceof User) {
            return;
        }

        if ($model->avatarConversionsPending()) {
            return;
        }

        UserAvatarReady::dispatch($model->id);
    }
}
