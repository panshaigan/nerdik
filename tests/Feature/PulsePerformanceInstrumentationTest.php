<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Pulse\Recorders\SlowLivewireActions;
use App\Support\Performance\PersistenceTiming;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Laravel\Pulse\Entry;
use Laravel\Pulse\Pulse;
use Laravel\Pulse\Recorders\CacheInteractions;
use Laravel\Pulse\Recorders\Exceptions;
use Laravel\Pulse\Recorders\SlowRequests;
use Laravel\Pulse\Recorders\UserRequests;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

final class PulsePerformanceInstrumentationTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        app(Pulse::class)->startRecording()->flush();
    }

    protected function tearDown(): void
    {
        app(Pulse::class)->flush();

        parent::tearDown();
    }

    public function test_only_high_volume_recorders_are_sampled_by_default(): void
    {
        $this->assertSame(0.1, config('pulse.recorders.'.CacheInteractions::class.'.sample_rate'));
        $this->assertSame(0.1, config('pulse.recorders.'.UserRequests::class.'.sample_rate'));
        $this->assertSame(1, config('pulse.recorders.'.Exceptions::class.'.sample_rate'));
        $this->assertSame(1, config('pulse.recorders.'.SlowRequests::class.'.sample_rate'));
        $this->assertSame(1, config('pulse.recorders.'.SlowLivewireActions::class.'.sample_rate'));
    }

    public function test_checksum_failures_are_ignored_and_event_cache_keys_are_grouped(): void
    {
        $config = config('pulse.recorders.'.CacheInteractions::class);

        $this->assertContains('/^livewire-checksum-failures:.*/', $config['ignore']);
        $this->assertSame(
            'event_show.programme_stats.*.*',
            $config['groups']['/^event_show\\.programme_stats\\.v\\d+\\.\\d+$/'],
        );
    }

    public function test_slow_persistence_records_only_bounded_phase_aggregates(): void
    {
        config([
            'monitoring.persistence_timing.enabled' => true,
            'monitoring.persistence_timing.slow_threshold_ms' => 0,
        ]);

        $timing = app(PersistenceTiming::class)->start('activity.create');
        $timing->checkpoint('validation');
        $timing->recordIfSlow();

        $entries = $this->pulseEntries()->where('type', 'persistence_phase');

        $this->assertNotEmpty($entries);
        $this->assertContains('activity.create|validation', $entries->pluck('key')->all());
        $this->assertContains('activity.create|total', $entries->pluck('key')->all());
        $this->assertTrue($entries->every(fn (Entry $entry): bool => $entry->isAvg() && $entry->isMax() && $entry->isCount() && $entry->isOnlyBuckets()));
        $this->assertTrue($entries->every(fn (Entry $entry): bool => ! str_contains($entry->key, '@') && ! preg_match('/\d{3,}/', $entry->key)));
    }

    public function test_disabled_persistence_timing_records_nothing(): void
    {
        config(['monitoring.persistence_timing.enabled' => false]);

        $timing = app(PersistenceTiming::class)->start('event.create');
        $timing->checkpoint('validation');
        $timing->recordIfSlow();

        $this->assertCount(0, $this->pulseEntries()->where('type', 'persistence_phase'));
    }

    public function test_slow_livewire_request_records_component_and_action_without_payload_data(): void
    {
        config([
            'pulse.recorders.'.SlowLivewireActions::class.'.sample_rate' => 1,
            'pulse.recorders.'.SlowLivewireActions::class.'.threshold' => 0,
        ]);

        $snapshot = json_encode([
            'memo' => [
                'name' => 'profile.update-avatar-form',
                'path' => 'profile',
            ],
        ], JSON_THROW_ON_ERROR);
        $request = Request::create('/livewire-db4b429e/update', 'POST', [
            'components' => [[
                'snapshot' => $snapshot,
                'calls' => [[
                    'method' => 'updateAvatar',
                    'params' => ['private-value-that-must-not-be-recorded'],
                ]],
            ]],
        ]);
        $route = new Route(['POST'], '/livewire-db4b429e/update', fn () => null);
        $route->name('default-livewire.update');
        $request->setRouteResolver(fn () => $route);

        app(SlowLivewireActions::class)->record(
            Carbon::now()->subSeconds(2),
            $request,
            new Response,
        );

        $entry = $this->pulseEntries()->firstWhere('type', 'slow_livewire_action');
        $this->assertInstanceOf(Entry::class, $entry);

        $label = json_decode($entry->key, true, flags: JSON_THROW_ON_ERROR);
        $this->assertSame('/profile', $label['path']);
        $this->assertSame('profile.update-avatar-form', $label['component']);
        $this->assertSame('updateAvatar', $label['action']);
        $this->assertStringNotContainsString('private-value', $entry->key);
    }

    /** @return Collection<int, Entry> */
    private function pulseEntries(): Collection
    {
        $reflection = new \ReflectionProperty(Pulse::class, 'entries');

        return $reflection->getValue(app(Pulse::class));
    }
}
