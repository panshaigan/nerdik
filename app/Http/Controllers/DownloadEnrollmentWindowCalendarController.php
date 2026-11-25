<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Event;
use App\Models\EventEnrollmentWindow;
use App\Support\Calendar\CalendarLinks;
use Symfony\Component\HttpFoundation\Response;

final class DownloadEnrollmentWindowCalendarController extends Controller
{
    public function __invoke(
        Event $event,
        EventEnrollmentWindow $enrollmentWindow,
        CalendarLinks $calendarLinks,
    ): Response {
        abort_unless($event->isVisibleTo(auth()->user()), 404);
        abort_unless((int) $enrollmentWindow->event_id === (int) $event->id, 404);

        $payload = $calendarLinks->forEnrollmentWindow($enrollmentWindow);
        abort_if($payload === null, 404);

        return response($calendarLinks->icsContent($payload), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$payload->downloadFilename.'"',
        ]);
    }
}
