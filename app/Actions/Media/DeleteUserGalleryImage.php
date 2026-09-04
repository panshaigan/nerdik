<?php

declare(strict_types=1);

namespace App\Actions\Media;

use App\Models\User;
use App\Support\Media\UserGalleryCatalog;
use Illuminate\Auth\Access\AuthorizationException;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class DeleteUserGalleryImage
{
    public function __construct(
        private UserGalleryCatalog $catalog,
    ) {}

    public function __invoke(User $user, Media|int $media): void
    {
        $mediaId = $media instanceof Media ? (int) $media->id : $media;
        $owned = $this->catalog->findForUser($mediaId, $user);

        if ($owned === null) {
            throw new AuthorizationException('Gallery image does not belong to this user.');
        }

        $owned->delete();
    }
}
