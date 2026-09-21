<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\EventSeries;
use App\Support\Calendar\CalendarLinks;
use Symfony\Component\HttpFoundation\Response;

final class DownloadEventSeriesCalendarController extends Controller
{
    public function __invoke(EventSeries $eventSeries, CalendarLinks $calendarLinks): Response
    {
        abort_unless($eventSeries->isVisibleTo(auth()->user()), 404);

        $payload = $calendarLinks->forEventSeries(
            $eventSeries,
            $eventSeries->visibleUpcomingEvents(auth()->user()),
        );
        abort_if($payload === null, 404);

        return response($calendarLinks->icsContentMany($payload->events), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$payload->downloadFilename.'"',
        ]);
    }
}
