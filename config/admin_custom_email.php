<?php

declare(strict_types=1);

return [

    'max_recipients' => (int) env('ADMIN_CUSTOM_EMAIL_MAX_RECIPIENTS', 100),

    'sends_per_minute' => (int) env('ADMIN_CUSTOM_EMAIL_SENDS_PER_MINUTE', 30),

];
