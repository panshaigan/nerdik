<?php

declare(strict_types=1);

namespace App\Support\Seo;

use App\Models\Activity;
use App\Models\Event;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\Cache;

final class SitemapBuilder
{
    public function xml(): string
    {
        $ttl = max(0, (int) config('seo.sitemap_cache_seconds', 3600));

        if ($ttl === 0) {
            return $this->render();
        }

        return Cache::remember('seo.sitemap.xml', $ttl, fn (): string => $this->render());
    }

    public function render(): string
    {
        $urls = [
            ...$this->staticUrls(),
            ...$this->publicEventUrls(),
            ...$this->publicActivityUrls(),
        ];

        $body = collect($urls)
            ->map(fn (array $entry): string => $this->urlElement($entry['loc'], $entry['lastmod'] ?? null))
            ->implode('');

        return '<?xml version="1.0" encoding="UTF-8"?>'
            ."\n"
            .'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'
            ."\n"
            .$body
            .'</urlset>';
    }

    /**
     * @return list<array{loc: string, lastmod: ?CarbonInterface}>
     */
    private function staticUrls(): array
    {
        return [
            ['loc' => url('/'), 'lastmod' => null],
            ['loc' => route('search.index'), 'lastmod' => null],
            ['loc' => route('catalog.places'), 'lastmod' => null],
            ['loc' => route('catalog.organizations'), 'lastmod' => null],
            ['loc' => route('catalog.series'), 'lastmod' => null],
            ['loc' => route('privacy'), 'lastmod' => null],
            ['loc' => route('terms'), 'lastmod' => null],
            ['loc' => route('contact'), 'lastmod' => null],
        ];
    }

    /**
     * @return list<array{loc: string, lastmod: ?CarbonInterface}>
     */
    private function publicEventUrls(): array
    {
        $urls = [];

        Event::query()
            ->where('is_public', true)
            ->whereNull('cancelled_at')
            ->orderBy('id')
            ->select(['id', 'slug', 'updated_at'])
            ->lazyById()
            ->each(function (Event $event) use (&$urls): void {
                $urls[] = [
                    'loc' => route('events.show', $event),
                    'lastmod' => $event->updated_at,
                ];
            });

        return $urls;
    }

    /**
     * @return list<array{loc: string, lastmod: ?CarbonInterface}>
     */
    private function publicActivityUrls(): array
    {
        $urls = [];

        Activity::query()
            ->attachedToPublicEvent(false)
            ->orderBy('id')
            ->select(['id', 'slug', 'updated_at', 'hosting_mode', 'cancelled_at', 'starts_at', 'ends_at'])
            ->lazyById()
            ->each(function (Activity $activity) use (&$urls): void {
                $urls[] = [
                    'loc' => route('activities.show', $activity),
                    'lastmod' => $activity->updated_at,
                ];
            });

        return $urls;
    }

    private function urlElement(string $loc, ?CarbonInterface $lastmod): string
    {
        $xml = "  <url>\n"
            .'    <loc>'.e($loc)."</loc>\n";

        if ($lastmod !== null) {
            $xml .= '    <lastmod>'.$lastmod->toAtomString()."</lastmod>\n";
        }

        return $xml."  </url>\n";
    }
}
