<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\ActivitySeries;
use App\Support\Calendar\CalendarLinks;
use Symfony\Component\HttpFoundation\Response;

final class DownloadActivitySeriesCalendarController extends Controller
{
    public function __invoke(ActivitySeries $activitySeries, CalendarLinks $calendarLinks): Response
    {
        abort_unless($activitySeries->isVisibleTo(auth()->user()), 404);

        $payload = $calendarLinks->forActivitySeries(
            $activitySeries,
            $activitySeries->visibleUpcomingActivities(auth()->user()),
        );
        abort_if($payload === null, 404);

        return response($calendarLinks->icsContentMany($payload->events), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$payload->downloadFilename.'"',
        ]);
    }
}
