<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Remaining places ratio threshold
    |--------------------------------------------------------------------------
    |
    | Followers of an activity or event are notified when remaining capacity
    | crosses at or below this ratio of max capacity (e.g. 0.25 = 25% left).
    |
    */

    'remaining_ratio_threshold' => (float) env('INTERESTED_PLACES_REMAINING_RATIO', 0.25),

];
