<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Support\Calendar\CalendarLinks;
use Symfony\Component\HttpFoundation\Response;

final class DownloadEventCalendarController extends Controller
{
    public function __invoke(Event $event, CalendarLinks $calendarLinks): Response
    {
        abort_unless($event->isVisibleTo(auth()->user()), 404);

        $payload = $calendarLinks->forEvent($event);
        abort_if($payload === null, 404);

        return response($calendarLinks->icsContent($payload), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$payload->downloadFilename.'"',
        ]);
    }
}
