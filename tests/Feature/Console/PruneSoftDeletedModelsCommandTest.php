<?php

declare(strict_types=1);

namespace Tests\Feature\Console;

use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PruneSoftDeletedModelsCommandTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function it_force_deletes_soft_deleted_models_past_retention(): void
    {
        Config::set('housekeeping.soft_deleted_retention_days', 90);

        $expired = Organization::factory()->create();
        $expired->delete();
        $expired->forceFill(['deleted_at' => now()->subDays(91)])->saveQuietly();

        $recent = Organization::factory()->create();
        $recent->delete();
        $recent->forceFill(['deleted_at' => now()->subDays(10)])->saveQuietly();

        $this->artisan('model:prune', ['--model' => [Organization::class]])
            ->assertSuccessful();

        $this->assertDatabaseMissing('organizations', ['id' => $expired->id]);
        $this->assertSoftDeleted('organizations', ['id' => $recent->id]);
    }

    #[Test]
    public function pretend_does_not_delete_soft_deleted_models(): void
    {
        Config::set('housekeeping.soft_deleted_retention_days', 90);

        $expired = Organization::factory()->create();
        $expired->delete();
        $expired->forceFill(['deleted_at' => now()->subDays(91)])->saveQuietly();

        $this->artisan('model:prune', [
            '--model' => [Organization::class],
            '--pretend' => true,
        ])->assertSuccessful();

        $this->assertSoftDeleted('organizations', ['id' => $expired->id]);
    }
}
