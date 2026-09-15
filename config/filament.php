<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Admin panel URL path
    |--------------------------------------------------------------------------
    |
    | Path segment for the Filament admin panel (e.g. "admin" → /admin).
    | Use a non-obvious value in staging/production to reduce casual discovery.
    | Panel id stays "admin" so named routes remain filament.admin.*.
    |
    */

    'admin_path' => env('FILAMENT_ADMIN_PATH', 'admin'),

];
