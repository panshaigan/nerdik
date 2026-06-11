<?php

return [
    'daily_send_time' => env('SCHEDULED_NOTIFICATIONS_DAILY_SEND_TIME', '09:00'),
    'timezone_fallback' => env('SCHEDULED_NOTIFICATIONS_TIMEZONE_FALLBACK', config('app.timezone', 'UTC')),
    'lookahead_hours' => (int) env('SCHEDULED_NOTIFICATIONS_LOOKAHEAD_HOURS', 24),
];
