<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Uncapped activity participants counter threshold
    |--------------------------------------------------------------------------
    |
    | When an activity has no max_participants, listing cards and counters hide
    | the participant count until filled exceeds this value (strictly greater).
    |
    */

    'uncapped_participants_counter_threshold' => (int) env(
        'ACTIVITY_UNCAPPED_PARTICIPANTS_COUNTER_THRESHOLD',
        6
    ),

];
