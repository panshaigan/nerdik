<?php

declare(strict_types=1);

namespace App\Support\Calendar;

use Carbon\CarbonInterface;

final class IcsGenerator
{
    public function generate(CalendarPayload $payload): string
    {
        return $this->generateMany([$payload]);
    }

    /**
     * @param  list<CalendarPayload>  $payloads
     */
    public function generateMany(array $payloads): string
    {
        if ($payloads === []) {
            return '';
        }

        $now = now('UTC');
        $method = $payloads[0]->method;

        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Nerdik//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:'.$method->value,
        ];

        foreach ($payloads as $payload) {
            array_push($lines, ...$this->veventLines($payload, $now));
        }

        $lines[] = 'END:VCALENDAR';

        $folded = array_map(fn (string $line): string => $this->foldLine($line), $lines);

        return implode("\r\n", $folded)."\r\n";
    }

    public function formatUtc(CarbonInterface $dateTime): string
    {
        return $dateTime->copy()->utc()->format('Ymd\THis\Z');
    }

    public function escapeText(string $value): string
    {
        $value = str_replace(["\r\n", "\r", "\n"], '\n', $value);

        return str_replace(
            ['\\', ';', ','],
            ['\\\\', '\;', '\,'],
            $value,
        );
    }

    /**
     * Fold lines to 75 octets per RFC 5545.
     */
    public function foldLine(string $line): string
    {
        if (strlen($line) <= 75) {
            return $line;
        }

        $parts = [];
        $remaining = $line;
        $firstChunk = 75;

        while ($remaining !== '') {
            $chunkSize = $parts === [] ? $firstChunk : 74;
            if (strlen($remaining) <= $chunkSize) {
                $parts[] = ($parts === [] ? '' : ' ').$remaining;
                break;
            }

            $parts[] = ($parts === [] ? '' : ' ').substr($remaining, 0, $chunkSize);
            $remaining = substr($remaining, $chunkSize);
        }

        return implode("\r\n", $parts);
    }

    /**
     * @return list<string>
     */
    private function veventLines(CalendarPayload $payload, CarbonInterface $now): array
    {
        $status = $payload->method === IcsMethod::Cancel ? 'CANCELLED' : 'CONFIRMED';

        $lines = [
            'BEGIN:VEVENT',
            'UID:'.$payload->uid,
            'DTSTAMP:'.$this->formatUtc($now),
            'DTSTART:'.$this->formatUtc($payload->startsAt),
            'DTEND:'.$this->formatUtc($payload->endsAt),
            'SEQUENCE:'.$payload->sequence,
            'STATUS:'.$status,
            'SUMMARY:'.$this->escapeText($payload->title),
        ];

        if ($payload->description !== '') {
            $lines[] = 'DESCRIPTION:'.$this->escapeText($payload->description);
        }

        if ($payload->location !== '') {
            $lines[] = 'LOCATION:'.$this->escapeText($payload->location);
        }

        if ($payload->hasCoordinates()) {
            $lines[] = sprintf(
                'GEO:%s;%s',
                $this->formatCoordinate((float) $payload->latitude),
                $this->formatCoordinate((float) $payload->longitude),
            );
        }

        if ($payload->url !== '') {
            $lines[] = 'URL:'.$payload->url;
        }

        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function formatCoordinate(float $value): string
    {
        return rtrim(rtrim(sprintf('%.6F', $value), '0'), '.');
    }
}
