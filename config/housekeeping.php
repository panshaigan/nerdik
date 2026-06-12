<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Failed Jobs Retention
    |--------------------------------------------------------------------------
    |
    | Hours to retain failed job records before queue:prune-failed removes them.
    |
    */

    'failed_jobs_hours' => (int) env('HOUSEKEEPING_FAILED_JOBS_HOURS', 168),

    /*
    |--------------------------------------------------------------------------
    | Job Batches Retention
    |--------------------------------------------------------------------------
    |
    | Hours to retain finished job batch records before queue:prune-batches
    | removes them.
    |
    */

    'job_batches_hours' => (int) env('HOUSEKEEPING_JOB_BATCHES_HOURS', 48),

    /*
    |--------------------------------------------------------------------------
    | Session Grace Period
    |--------------------------------------------------------------------------
    |
    | Extra days beyond session.lifetime before expired session rows are deleted
    | from the database sessions table.
    |
    */

    'sessions_grace_days' => (int) env('HOUSEKEEPING_SESSIONS_GRACE_DAYS', 7),

    /*
    |--------------------------------------------------------------------------
    | Livewire Temporary Uploads
    |--------------------------------------------------------------------------
    |
    | Hours to retain abandoned Livewire temporary upload files.
    |
    */

    'livewire_tmp_hours' => (int) env('HOUSEKEEPING_LIVEWIRE_TMP_HOURS', 24),

    /*
    |--------------------------------------------------------------------------
    | Application Log Retention
    |--------------------------------------------------------------------------
    |
    | Days to retain rotated log files under storage/logs. Reuses LOG_DAILY_DAYS
    | when set, matching the Monolog daily driver configuration.
    |
    */

    'log_retention_days' => (int) env('LOG_DAILY_DAYS', 14),

    /*
    |--------------------------------------------------------------------------
    | Sync Temp Backup Retention
    |--------------------------------------------------------------------------
    |
    | Days to retain /tmp/nerdik-*-backup-* directories created during sync
    | imports with BACKUP=1 on the VPS host.
    |
    */

    'tmp_backup_retention_days' => (int) env('HOUSEKEEPING_TMP_BACKUP_RETENTION_DAYS', 7),

];
