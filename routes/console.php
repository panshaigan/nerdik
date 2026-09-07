<?php

use App\Jobs\SendWorkerMonitoringHeartbeatJob;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Laravel\Telescope\Telescope;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('telescope:prune')
    ->daily()
    ->when(fn (): bool => class_exists(Telescope::class)
        && (bool) config('telescope.enabled', false));
Schedule::command('notifications:scheduled-digest')->hourly()->withoutOverlapping();
Schedule::command('user-requests:expire')->hourly()->withoutOverlapping();
Schedule::command('activities:resolve-lotteries')->everyMinute()->withoutOverlapping();
Schedule::command('tags:recalculate-popularity')->everySixHours()->withoutOverlapping();

Schedule::command('monitoring:heartbeat', ['scheduler'])
    ->everyMinute()
    ->when(fn (): bool => filled(config('monitoring.scheduler_heartbeat_url')))
    ->withoutOverlapping();

Schedule::job(new SendWorkerMonitoringHeartbeatJob)
    ->everyMinute()
    ->when(fn (): bool => filled(config('monitoring.worker_heartbeat_url')))
    ->withoutOverlapping();

Schedule::command('auth:clear-resets')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('queue:prune-failed', [
    '--hours' => config('housekeeping.failed_jobs_hours'),
])->dailyAt('03:30')->withoutOverlapping();
Schedule::command('queue:prune-batches', [
    '--hours' => config('housekeeping.job_batches_hours'),
])->dailyAt('03:30')->withoutOverlapping();
Schedule::command('housekeeping:prune-sessions')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('housekeeping:prune-cache')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('housekeeping:prune-livewire-uploads')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('housekeeping:prune-logs')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('housekeeping:prune-sent-emails')->dailyAt('03:30')->withoutOverlapping();
Schedule::command('media-library:clean', [
    '--delete-orphaned' => true,
    '--force' => true,
])->weeklyOn(0, '04:00')->withoutOverlapping();

// Schedule::command('model:prune')->daily();
