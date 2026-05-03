<?php

return [
    'daily_send_time' => env('SCHEDULED_NOTIFICATIONS_DAILY_SEND_TIME', '09:00'),
    'timezone_fallback' => env('SCHEDULED_NOTIFICATIONS_TIMEZONE_FALLBACK', config('app.timezone', 'UTC')),
    'organizer_unanswered_proposals' => [
        'baseline_weekdays' => [1, 4], // Monday, Thursday
        'daily_escalation_window_days' => 7,
    ],
    'days_before' => [
        'enrollment_window' => [1, 0],
        'dashboard_feed' => [3],
        'participant_cancellation_deadline' => [2],
        'host_low_participation' => [1],
    ],
];
