<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\PageAlias;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PageAliasRedirectTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function existing_alias_redirects_with_302_and_increments_hits(): void
    {
        $alias = PageAlias::factory()->create([
            'alias' => 'my-board',
            'target_path' => '/dashboard',
            'hits' => 0,
        ]);

        $response = $this->get('/my-board');

        $response->assertRedirect('/dashboard');
        $response->assertStatus(302);
        $this->assertSame(1, $alias->fresh()->hits);
    }

    #[Test]
    public function unknown_single_segment_path_returns_404(): void
    {
        $this->get('/no-such-alias-xyz')->assertNotFound();
    }

    #[Test]
    public function unknown_multi_segment_path_returns_404(): void
    {
        $this->get('/no/such/path')->assertNotFound();
    }

    #[Test]
    public function registered_routes_are_not_shadowed_by_aliases(): void
    {
        PageAlias::factory()->create([
            'alias' => 'features',
            'target_path' => '/dashboard',
        ]);

        $this->get('/features')->assertOk();
    }
}
