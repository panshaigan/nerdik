<?php

return [

    /*
    |--------------------------------------------------------------------------
    | UptimeRobot (or compatible) Heartbeat URLs
    |--------------------------------------------------------------------------
    |
    | When set, the scheduler pings these URLs every minute. Create matching
    | heartbeat monitors in UptimeRobot. Leave empty to disable.
    |
    | - scheduler: pinged directly by schedule:work (proves the scheduler is alive)
    | - worker: pinged from a queued job (proves the queue worker is processing)
    |
    */

    'scheduler_heartbeat_url' => env('MONITORING_SCHEDULER_HEARTBEAT_URL'),

    'worker_heartbeat_url' => env('MONITORING_WORKER_HEARTBEAT_URL'),

    'persistence_timing' => [
        'enabled' => env('MONITORING_PERSISTENCE_TIMING_ENABLED', true),
        'slow_threshold_ms' => env('MONITORING_PERSISTENCE_SLOW_THRESHOLD_MS', 1000),
    ],

];
