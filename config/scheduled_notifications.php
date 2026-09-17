<?php

return [
    'daily_send_time' => env('SCHEDULED_NOTIFICATIONS_DAILY_SEND_TIME', '09:00'),
    'timezone_fallback' => env('SCHEDULED_NOTIFICATIONS_TIMEZONE_FALLBACK', 'Europe/Warsaw'),
    'lookahead_hours' => (int) env('SCHEDULED_NOTIFICATIONS_LOOKAHEAD_HOURS', 24),
    /*
     | Daily digest for event organizers while slotted activities are under
     | min_participants and the event's local start date falls within the next
     | N calendar days (inclusive of today).
     */
    'organizer_low_participation_runway_days' => (int) env('SCHEDULED_NOTIFICATIONS_ORGANIZER_LOW_PARTICIPATION_RUNWAY_DAYS', 7),
    /*
     | Host digest days relative to the cancellation deadline (local calendar),
     | e.g. 3 = three days before the deadline, 1 = the day before (tomorrow).
     */
    'host_low_participation_deadline_offsets' => [3, 1],
];
