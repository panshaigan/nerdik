<?php

declare(strict_types=1);

namespace App\Actions\Avatars;

use App\Enums\AvatarSource;
use App\Models\User;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use RuntimeException;

final class RefreshCachedAvatar
{
    private const int AVATAR_SIZE = 512;

    private const int WEBP_QUALITY = 85;

    private const int MAX_DOWNLOAD_BYTES = 5_000_000;

    public function __construct(
        private ImageManager $manager,
    ) {}

    /**
     * Downloads the remote avatar, re-encodes to WEBP, and updates the user's profile.
     *
     * @throws RuntimeException When the remote image is missing or invalid
     */
    public function __invoke(User $user, AvatarSource $source): void
    {
        if (! $source->usesRemoteCache()) {
            throw new RuntimeException('Source does not use remote avatar cache.');
        }

        $profile = $user->profile()->firstOrCreate();
        $url = $this->resolveRemoteUrl($user, $source);

        if ($url === null || $url === '') {
            throw new RuntimeException('No remote avatar URL is available for this source.');
        }

        $response = Http::timeout(25)
            ->withHeaders([
                'User-Agent' => 'NerdikAvatarFetcher/1.0',
                'Accept' => 'image/*,*/*;q=0.8',
            ])
            ->get($url);

        if (! $response->successful()) {
            throw new RuntimeException('Remote avatar could not be downloaded (HTTP '.$response->status().').');
        }

        $body = $response->body();
        if (strlen($body) > self::MAX_DOWNLOAD_BYTES) {
            throw new RuntimeException('Remote avatar exceeds maximum download size.');
        }

        $newSig = hash('sha256', $body);
        $relativePath = 'avatars/'.$user->id.'.webp';

        if ($newSig === (string) $profile->avatar_cache_signature
            && $profile->avatar_path === $relativePath
            && Storage::disk('public')->exists($relativePath)) {
            return;
        }

        $image = $this->manager->read($body)->cover(self::AVATAR_SIZE, self::AVATAR_SIZE);
        $encoded = $image->toWebp(self::WEBP_QUALITY);

        Storage::disk('public')->put($relativePath, $encoded->toString(), [
            'visibility' => 'public',
        ]);

        $profile->avatar_path = $relativePath;
        $profile->avatar_cache_signature = $newSig;
        $profile->save();
        $user->setRelation('profile', $profile);
    }

    private function resolveRemoteUrl(User $user, AvatarSource $source): ?string
    {
        $profile = $user->profile;

        return match ($source) {
            AvatarSource::Gravatar => $this->gravatarUrlForEmail((string) $user->email),
            AvatarSource::Google => $profile?->google_avatar_url,
            AvatarSource::Facebook => $profile?->facebook_avatar_url,
            default => null,
        };
    }

    private function gravatarUrlForEmail(string $email): string
    {
        $hash = md5(strtolower(trim($email)));

        return 'https://www.gravatar.com/avatar/'.$hash.'?s='.self::AVATAR_SIZE.'&d=404';
    }
}
