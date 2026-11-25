<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PageAlias;
use App\Models\User;
use App\Services\PageAliasService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PageAliasServiceTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function rejects_reserved_alias_names(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(PageAliasService::class);

        $this->expectException(ValidationException::class);
        $service->create($admin, 'admin', '/dashboard');
    }

    #[Test]
    public function rejects_target_paths_that_do_not_match_a_route(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(PageAliasService::class);

        $this->expectException(ValidationException::class);
        $service->create($admin, 'valid-alias', '/not-a-real-page-here');
    }

    #[Test]
    public function rejects_external_target_paths(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(PageAliasService::class);

        $this->expectException(ValidationException::class);
        $service->create($admin, 'valid-alias', 'https://example.com/dashboard');
    }

    #[Test]
    public function allows_multiple_aliases_for_one_target(): void
    {
        $admin = User::factory()->admin()->create();
        $service = app(PageAliasService::class);

        $first = $service->create($admin, 'dash-one', '/dashboard');
        $second = $service->create($admin, 'dash-two', '/dashboard');

        $this->assertSame('/dashboard', $first->target_path);
        $this->assertSame('/dashboard', $second->target_path);
        $this->assertNotSame($first->alias, $second->alias);
    }

    #[Test]
    public function rejects_when_alias_already_exists(): void
    {
        PageAlias::factory()->create(['alias' => 'taken-name', 'target_path' => '/dashboard']);
        $admin = User::factory()->admin()->create();
        $service = app(PageAliasService::class);

        $this->expectException(ValidationException::class);
        $service->create($admin, 'taken-name', '/features');
    }
}
