<?php

declare(strict_types=1);

return [
    'default_expiration_days' => (int) env('USER_REQUEST_DEFAULT_EXPIRATION_DAYS', 14),
    'message_max_length' => 500,
    'organizer_request_cooldown_days' => (int) env('USER_REQUEST_ORGANIZER_COOLDOWN_DAYS', 30),
];
