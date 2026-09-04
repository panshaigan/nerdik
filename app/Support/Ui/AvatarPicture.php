<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Actions\Avatars\ResolveAvatarUrl;
use App\Enums\AvatarSource;
use App\Models\User;

final readonly class AvatarPicture
{
    public function __construct(
        public ?string $url = null,
        public ?string $fallbackUrl = null,
        public bool $isPendingPlaceholder = false,
    ) {}

    public static function fromUser(User $user, AvatarSlot $slot = AvatarSlot::Badge): self
    {
        $profile = $user->profile;
        $rawSource = $profile?->avatar_source;
        $source = $rawSource instanceof AvatarSource
            ? $rawSource
            : (AvatarSource::tryFrom((string) ($rawSource ?? '')) ?? null);

        if ($source === AvatarSource::Gallery) {
            if ($user->avatarConversionsPending()) {
                $originalUrl = $user->pendingAvatarOriginalUrl();

                if (is_string($originalUrl) && $originalUrl !== '') {
                    return new self(url: $originalUrl);
                }
            }

            $avatarMedia = $user->getFirstMedia('avatar');

            if ($avatarMedia !== null) {
                if ($avatarMedia->hasGeneratedConversion($slot->conversionName())) {
                    return new self(url: $avatarMedia->getUrl($slot->conversionName()));
                }

                return new self(url: $avatarMedia->getUrl());
            }

            $galleryMedia = $profile?->relationLoaded('galleryMedia')
                ? $profile->galleryMedia
                : $profile?->galleryMedia()->first();

            if ($galleryMedia !== null) {
                $url = $galleryMedia->hasGeneratedConversion('webp')
                    ? $galleryMedia->getUrl('webp')
                    : $galleryMedia->getUrl();

                return new self(url: $url);
            }

            return new self;
        }

        if ($user->avatarConversionsPending()) {
            $originalUrl = $user->pendingAvatarOriginalUrl();

            if (is_string($originalUrl) && $originalUrl !== '') {
                return new self(url: $originalUrl);
            }

            return new self(
                fallbackUrl: $user->generatedAvatarUrl($slot->displaySize()),
                isPendingPlaceholder: true,
            );
        }

        $media = $user->getFirstMedia('avatar');

        if ($media !== null && $media->hasGeneratedConversion($slot->conversionName())) {
            return new self(url: $media->getUrl($slot->conversionName()));
        }

        return new self;
    }

    public function hasDisplayableImage(): bool
    {
        return $this->url !== null;
    }

    public function resolvedUrl(User $user, AvatarSlot $slot = AvatarSlot::Badge): string
    {
        if ($this->fallbackUrl !== null) {
            return $this->fallbackUrl;
        }

        if ($this->url !== null) {
            return $this->url;
        }

        return app(ResolveAvatarUrl::class)($user, $slot);
    }
}
