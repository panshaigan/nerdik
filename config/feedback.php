<?php

declare(strict_types=1);

return [
    /*
    |--------------------------------------------------------------------------
    | Feedback reply templates
    |--------------------------------------------------------------------------
    |
    | Keys listed here appear in the Filament reply action. Copy lives under
    | lang/{locale}/feedback.php → templates.{key}.
    |
    */
    'reply_templates' => [
        'thanks',
        'need_more_info',
        'looking_into_it',
        'resolved',
        'wont_change',
    ],

    /*
    |--------------------------------------------------------------------------
    | Submission rate limits
    |--------------------------------------------------------------------------
    */
    'submit_max_attempts' => 5,
    'submit_decay_seconds' => 3600,

    'upload_max_attempts' => 10,
    'upload_decay_minutes' => 1,

    'upload_session_bytes' => 10 * 1024 * 1024,
    'upload_daily_ip_bytes' => 20 * 1024 * 1024,
    'upload_pending_bytes' => 256 * 1024 * 1024,
];
