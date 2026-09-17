<?php

declare(strict_types=1);

namespace App\Notifications\Concerns;

use App\Support\Calendar\CalendarLinks;
use App\Support\Calendar\CalendarPayload;
use Illuminate\Notifications\Messages\MailMessage;

trait AttachesCalendarIcs
{
    protected function attachCalendarIcs(MailMessage $message, ?CalendarPayload $payload): MailMessage
    {
        if ($payload === null) {
            return $message;
        }

        $ics = app(CalendarLinks::class)->icsContent($payload);

        return $message->attachData($ics, $payload->downloadFilename, [
            'mime' => 'text/calendar; charset=UTF-8; method='.$payload->method->value,
        ]);
    }
}
