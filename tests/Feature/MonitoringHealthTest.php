<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Events\DiagnosingHealth;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use RuntimeException;
use Tests\TestCase;

class MonitoringHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_when_database_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_fails_when_diagnosing_health_throws(): void
    {
        Event::listen(DiagnosingHealth::class, function (): void {
            throw new RuntimeException('forced health check failure');
        });

        $this->get('/up')->assertStatus(500);
    }
}
