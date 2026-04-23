<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Datetime UI Defaults
    |--------------------------------------------------------------------------
    |
    | minute_step is used for all datetime-local pickers in the UI.
    | Native datetime-local "step" is in seconds, so views should use:
    | config('ui-datetime.minute_step', 5) * 60
    |
    */
    'minute_step' => 1,
];
