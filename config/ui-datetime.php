<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Datetime UI Defaults
    |--------------------------------------------------------------------------
    |
    | minute_step is used for Flatpickr datetime pickers (data-ui-datetime).
    | Views may still pass step in seconds for markup compatibility:
    | config('ui-datetime.minute_step', 5) * 60
    |
    */
    'minute_step' => 1,

    /** Value shown in Flatpickr / datetime text inputs (space between date and time). */
    'input_format' => 'Y-m-d H:i',
];
