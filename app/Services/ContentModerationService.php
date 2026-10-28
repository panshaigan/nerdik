<?php

declare(strict_types=1);

namespace App\Services;

use App\Actions\Activities\DeleteUploadedActivityLogo;
use App\Actions\Avatars\ResolveAvatarUrl;
use App\Actions\Events\DeleteUploadedEventLogo;
use App\Enums\AvatarSource;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Support\Ui\ActivityListingImageResolver;
use App\Support\Ui\EventListingImageResolver;
use App\Support\Ui\ListingCardPicture;

final class ContentModerationService
{
    public function __construct(
        private readonly DeleteUploadedActivityLogo $deleteUploadedActivityLogo,
        private readonly DeleteUploadedEventLogo $deleteUploadedEventLogo,
        private readonly ActivityListingImageResolver $activityListingImageResolver,
        private readonly EventListingImageResolver $eventListingImageResolver,
        private readonly ResolveAvatarUrl $resolveAvatarUrl,
    ) {}

    public function removedPlaceholder(): string
    {
        return (string) __('ui.moderation.removed_placeholder');
    }

    public function profilePreviewImageUrl(User $user): ?string
    {
        $url = ($this->resolveAvatarUrl)($user);

        return $url !== '' ? $url : null;
    }

    public function activityPreviewImageUrl(Activity $activity): ?string
    {
        return $this->listingPictureUrl(
            $this->activityListingImageResolver->resolve($activity),
        );
    }

    public function eventPreviewImageUrl(Event $event): ?string
    {
        return $this->listingPictureUrl(
            $this->eventListingImageResolver->resolve($event),
        );
    }

    public function hasUserChosenProfileImage(User $user): bool
    {
        $profile = $user->profile;

        if ($profile === null) {
            return $user->getFirstMedia('avatar') !== null;
        }

        $source = $profile->avatar_source;

        if ($source === null) {
            return $user->getFirstMedia('avatar') !== null;
        }

        return $source !== AvatarSource::Generated;
    }

    public function hasUserChosenActivityImage(Activity $activity): bool
    {
        return $activity->logo_source !== null;
    }

    public function hasUserChosenEventImage(Event $event): bool
    {
        return $event->logo_source !== null;
    }

    public function removeProfilePicture(User $user): void
    {
        $user->clearMediaCollection('avatar');
        $user->clearMediaCollection('source');

        $profile = $user->profile;

        if ($profile !== null) {
            $profile->forceFill([
                'avatar_source' => AvatarSource::Generated,
                'gallery_media_id' => null,
            ])->save();
        }
    }

    public function removeActivityPicture(Activity $activity): void
    {
        ($this->deleteUploadedActivityLogo)($activity);

        $activity->forceFill([
            'logo_source' => null,
            'tag_media_id' => null,
            'gallery_media_id' => null,
            'logo_path' => null,
        ])->save();
    }

    public function removeEventPicture(Event $event): void
    {
        ($this->deleteUploadedEventLogo)($event);

        $event->forceFill([
            'logo_source' => null,
            'listing_media_id' => null,
            'gallery_media_id' => null,
            'logo_path' => null,
        ])->save();
    }

    public function applyPlaceholderToProfile(User $user): void
    {
        $user->forceFill([
            'nickname' => $this->uniqueModerationNickname($user),
        ])->save();
    }

    public function updateProfileNickname(User $user, string $nickname): void
    {
        $nickname = trim($nickname);

        if ($nickname === '') {
            return;
        }

        $user->forceFill(['nickname' => $nickname])->save();
    }

    public function applyPlaceholderToActivity(Activity $activity): void
    {
        $placeholder = $this->removedPlaceholder();

        $this->updateActivityText($activity, $placeholder, $placeholder);
    }

    public function applyPlaceholderToEvent(Event $event): void
    {
        $placeholder = $this->removedPlaceholder();

        $this->updateEventText($event, $placeholder, $placeholder);
    }

    public function updateActivityText(Activity $activity, string $name, ?string $description): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $originalSlug = (string) $activity->slug;

        $activity->forceFill([
            'name' => $name,
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
        ])->save();

        if ((string) $activity->slug !== $originalSlug) {
            $activity->updateQuietly(['slug' => $originalSlug]);
        }
    }

    public function updateEventText(Event $event, string $name, ?string $description): void
    {
        $name = trim($name);

        if ($name === '') {
            return;
        }

        $originalSlug = (string) $event->slug;

        $event->forceFill([
            'name' => $name,
            'description' => $description !== null && trim($description) !== '' ? trim($description) : null,
        ])->save();

        if ((string) $event->slug !== $originalSlug) {
            $event->updateQuietly(['slug' => $originalSlug]);
        }
    }

    private function uniqueModerationNickname(User $user): string
    {
        $base = 'removed_'.$user->id;

        if (! User::query()->where('nickname', $base)->whereKeyNot($user->id)->exists()) {
            return $base;
        }

        $suffix = 2;

        while (User::query()->where('nickname', $base.'_'.$suffix)->whereKeyNot($user->id)->exists()) {
            $suffix++;
        }

        return $base.'_'.$suffix;
    }

    private function listingPictureUrl(ListingCardPicture $picture): ?string
    {
        if (! $picture->hasDisplayableImage()) {
            return null;
        }

        $sources = $picture->sources;

        if ($sources === null) {
            return null;
        }

        $webp = $sources->webpSrc();

        return $webp !== '' ? $webp : null;
    }
}
