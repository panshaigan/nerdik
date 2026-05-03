<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class QueueAndScheduleConfigurationTest extends TestCase
{
    public function test_database_queue_connection_is_configured(): void
    {
        $this->assertSame('database', Config::get('queue.connections.database.driver'));
        $this->assertSame('jobs', Config::get('queue.connections.database.table'));
    }

    public function test_scheduler_commands_are_registered(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('telescope:prune')
            ->expectsOutputToContain('notifications:scheduled-digest')
            ->assertExitCode(0);
    }
}
