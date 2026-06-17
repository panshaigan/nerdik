<?php

declare(strict_types=1);

namespace App\Support\OAuth;

use Illuminate\Support\Facades\Http;

final class FacebookGraphProfileFetcher
{
    /**
     * @return array{name: ?string, link: ?string}|null
     */
    public function fetchMe(string $accessToken): ?array
    {
        if ($accessToken === '') {
            return null;
        }

        $version = (string) config('services.facebook.graph_version', 'v23.0');

        $response = Http::acceptJson()->get("https://graph.facebook.com/{$version}/me", [
            'fields' => 'link,name',
            'access_token' => $accessToken,
        ]);

        if (! $response->successful()) {
            return null;
        }

        $payload = $response->json();

        if (! is_array($payload)) {
            return null;
        }

        return [
            'name' => is_string($payload['name'] ?? null) && $payload['name'] !== '' ? $payload['name'] : null,
            'link' => is_string($payload['link'] ?? null) && $payload['link'] !== '' ? $payload['link'] : null,
        ];
    }
}
