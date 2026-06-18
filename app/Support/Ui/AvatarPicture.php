<?php

declare(strict_types=1);

namespace App\Support\Ui;

use App\Actions\Avatars\ResolveAvatarUrl;
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
