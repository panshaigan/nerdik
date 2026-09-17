<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Jobs\SendWorkerMonitoringHeartbeatJob;
use App\Models\User;
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

    public function test_sentry_config_is_injected_when_dsn_is_set(): void
    {
        Config::set('sentry.dsn', 'https://public@o0.ingest.sentry.io/0');
        Config::set('sentry.browser.ignore_errors', [
            'Object captured as promise rejection with keys: body, errors, json, status',
        ]);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('window.__nerdikSentry', false)
            ->assertSee('ingest.sentry.io', false)
            ->assertSee('Object captured as promise rejection with keys: body, errors, json, status', false);
    }

    public function test_sentry_config_is_omitted_when_dsn_is_empty(): void
    {
        Config::set('sentry.dsn', null);

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('window.__nerdikSentry', false);
    }

    public function test_umami_script_is_injected_when_website_id_is_set(): void
    {
        Config::set('umami.website_id', '94db1cb1-74f4-4a40-ad6c-962362670409');
        Config::set('umami.script_url', 'https://cloud.umami.is/script.js');
        Config::set('umami.domains', 'nerdik.app,www.nerdik.app');
        Config::set('umami.host_url', null);

        $this->get(route('login'))
            ->assertOk()
            ->assertSee('https://cloud.umami.is/script.js', false)
            ->assertSee('data-website-id="94db1cb1-74f4-4a40-ad6c-962362670409"', false)
            ->assertSee('data-tag="guest"', false)
            ->assertSee('data-domains="nerdik.app,www.nerdik.app"', false);
    }

    public function test_umami_script_uses_authenticated_tag_for_signed_in_users(): void
    {
        Config::set('umami.website_id', '94db1cb1-74f4-4a40-ad6c-962362670409');
        Config::set('umami.script_url', 'https://cloud.umami.is/script.js');

        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('data-tag="authenticated"', false)
            ->assertDontSee('data-tag="guest"', false);
    }

    public function test_umami_script_is_omitted_when_website_id_is_empty(): void
    {
        Config::set('umami.website_id', null);
        Config::set('umami.script_url', 'https://cloud.umami.is/script.js');

        $this->get(route('login'))
            ->assertOk()
            ->assertDontSee('cloud.umami.is/script.js', false)
            ->assertDontSee('data-website-id=', false);
    }
}
