<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendWorkerMonitoringHeartbeatJob;
use App\Services\Monitoring\MonitoringHeartbeat;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MonitoringTest extends TestCase
{
    public function test_scheduler_heartbeat_command_pings_configured_url(): void
    {
        Config::set('monitoring.scheduler_heartbeat_url', 'https://heartbeat.example/scheduler');

        Http::fake([
            'https://heartbeat.example/scheduler' => Http::response('ok', 200),
        ]);

        $this->artisan('monitoring:heartbeat', ['target' => 'scheduler'])
            ->assertSuccessful();

        Http::assertSent(fn ($request): bool => $request->url() === 'https://heartbeat.example/scheduler');
    }

    public function test_scheduler_heartbeat_is_noop_without_url(): void
    {
        Config::set('monitoring.scheduler_heartbeat_url', null);

        Http::fake();

        $this->artisan('monitoring:heartbeat', ['target' => 'scheduler'])
            ->assertSuccessful();

        Http::assertNothingSent();
    }

    public function test_worker_heartbeat_job_pings_configured_url(): void
    {
        Config::set('monitoring.worker_heartbeat_url', 'https://heartbeat.example/worker');

        Http::fake([
            'https://heartbeat.example/worker' => Http::response('ok', 200),
        ]);

        (new SendWorkerMonitoringHeartbeatJob)->handle(app(MonitoringHeartbeat::class));

        Http::assertSent(fn ($request): bool => $request->url() === 'https://heartbeat.example/worker');
    }

    public function test_monitoring_heartbeat_tasks_are_registered_when_urls_configured(): void
    {
        Config::set('monitoring.scheduler_heartbeat_url', 'https://heartbeat.example/scheduler');
        Config::set('monitoring.worker_heartbeat_url', 'https://heartbeat.example/worker');

        $this->artisan('schedule:list')
            ->expectsOutputToContain('monitoring:heartbeat')
            ->expectsOutputToContain(SendWorkerMonitoringHeartbeatJob::class)
            ->assertExitCode(0);
    }

    public function test_sentry_config_is_injected_when_dsn_is_set(): void
    {
        Config::set('sentry.dsn', 'https://public@o0.ingest.sentry.io/0');

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('window.__nerdikSentry', false)
            ->assertSee('ingest.sentry.io', false);
    }

    public function test_sentry_config_is_omitted_when_dsn_is_empty(): void
    {
        Config::set('sentry.dsn', null);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('window.__nerdikSentry', false);
    }
}
