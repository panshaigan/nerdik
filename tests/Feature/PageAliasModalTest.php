<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRequestType;
use App\Livewire\PageAliases\PageAliasModal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PageAliasModalTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function admin_submit_creates_alias(): void
    {
        $admin = User::factory()->admin()->create();

        Livewire::actingAs($admin)
            ->test(PageAliasModal::class)
            ->dispatch('open-page-alias-modal', path: '/dashboard')
            ->set('alias', 'from-modal')
            ->call('submit');

        $this->assertDatabaseHas('page_aliases', [
            'alias' => 'from-modal',
            'target_path' => '/dashboard',
        ]);
    }

    #[Test]
    public function user_submit_creates_pending_request(): void
    {
        $user = User::factory()->create();

        Livewire::actingAs($user)
            ->test(PageAliasModal::class)
            ->dispatch('open-page-alias-modal', path: '/features')
            ->set('alias', 'feat-link')
            ->call('submit');

        $this->assertDatabaseHas('user_requests', [
            'type' => UserRequestType::PageAlias->value,
            'requester_id' => $user->id,
        ]);

        $this->assertDatabaseMissing('page_aliases', [
            'alias' => 'feat-link',
        ]);
    }
}
