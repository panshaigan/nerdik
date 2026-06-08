<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Legal operator details
    |--------------------------------------------------------------------------
    |
    | Used in Privacy Policy, Terms of Service, and Contact pages.
    | Set LEGAL_* in .env before production launch.
    |
    */

    'operator_name' => env('LEGAL_OPERATOR_NAME'),

    'operator_country' => env('LEGAL_OPERATOR_COUNTRY', ''),

    'contact_email' => env('LEGAL_CONTACT_EMAIL'),

    'effective_date' => '8 June 2026',

];
