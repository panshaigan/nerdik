<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MonitoringHealthTest extends TestCase
{
    use RefreshDatabase;

    public function test_health_endpoint_returns_ok_when_database_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_health_endpoint_fails_when_database_is_unreachable(): void
    {
        Config::set('database.default', 'missing_monitoring_connection');
        Config::set('database.connections.missing_monitoring_connection', [
            'driver' => 'pgsql',
            'host' => '127.0.0.1',
            'port' => 1,
            'database' => 'missing',
            'username' => 'missing',
            'password' => 'missing',
        ]);

        DB::purge();

        $this->get('/up')->assertStatus(500);
    }
}
