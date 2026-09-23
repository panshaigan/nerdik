<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Support\Calendar\CalendarLinks;
use Symfony\Component\HttpFoundation\Response;

final class DownloadActivityCalendarController extends Controller
{
    public function __invoke(Activity $activity, CalendarLinks $calendarLinks): Response
    {
        abort_unless(
            $activity->isVisibleTo(auth()->user()),
            404
        );

        $payload = $calendarLinks->forActivity($activity);
        abort_if($payload === null, 404);

        return response($calendarLinks->icsContent($payload), 200, [
            'Content-Type' => 'text/calendar; charset=utf-8',
            'Content-Disposition' => 'inline; filename="'.$payload->downloadFilename.'"',
        ]);
    }
}
