<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Support\Sharing\ShareLinks;
use App\Support\Sharing\SharePayload;
use App\Support\Sharing\ShareTarget;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ShareRedirectController extends Controller
{
    public function __invoke(Request $request, string $target, ShareLinks $shareLinks): RedirectResponse
    {
        $shareTarget = ShareTarget::tryFrom($target);

        abort_unless($shareTarget instanceof ShareTarget && $shareTarget->isExternal(), 404);

        /** @var array{url: string, title: string, text?: string|null, campaign: string} $data */
        $data = $request->validate([
            'url' => ['required', 'string', 'max:2048'],
            'title' => ['required', 'string', 'max:255'],
            'text' => ['nullable', 'string', 'max:2000'],
            'campaign' => ['required', 'string', 'max:64'],
        ]);

        abort_unless($this->isAppOwnedUrl($data['url']), 404);

        $payload = new SharePayload(
            url: $data['url'],
            title: $data['title'],
            text: (string) ($data['text'] ?? ''),
            campaign: $data['campaign'],
        );

        $intentUrl = $shareLinks->intentUrl($payload, $shareTarget);

        abort_unless(is_string($intentUrl) && $intentUrl !== '', 404);

        return redirect()->away($intentUrl);
    }

    private function isAppOwnedUrl(string $url): bool
    {
        $parts = parse_url($url);
        $appParts = parse_url((string) config('app.url'));

        if (! is_array($parts) || ! is_array($appParts)) {
            return false;
        }

        if (! isset($parts['scheme'], $parts['host'], $appParts['scheme'], $appParts['host'])) {
            return false;
        }

        if (! in_array(strtolower((string) $parts['scheme']), ['http', 'https'], true)) {
            return false;
        }

        if (strtolower((string) $parts['host']) !== strtolower((string) $appParts['host'])) {
            return false;
        }

        $urlPort = $parts['port'] ?? $this->defaultPortForScheme((string) $parts['scheme']);
        $appPort = $appParts['port'] ?? $this->defaultPortForScheme((string) $appParts['scheme']);

        return $urlPort === $appPort;
    }

    private function defaultPortForScheme(string $scheme): int
    {
        return strtolower($scheme) === 'https' ? 443 : 80;
    }
}
