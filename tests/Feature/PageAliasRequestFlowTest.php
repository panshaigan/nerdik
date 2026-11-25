<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\PageAlias;
use App\Models\User;
use App\Notifications\UserRequestReceivedNotification;
use App\Services\PageAliasService;
use App\Services\UserRequests\UserRequestDecisionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PageAliasRequestFlowTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function user_request_notifies_admins_and_accept_creates_alias(): void
    {
        Notification::fake();

        $requester = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $request = app(PageAliasService::class)->request($requester, 'go-dash', '/dashboard');

        $this->assertSame(UserRequestType::PageAlias, $request->type);
        Notification::assertSentTo($admin, UserRequestReceivedNotification::class);

        app(UserRequestDecisionService::class)->accept($request, $admin);

        $this->assertDatabaseHas('page_aliases', [
            'alias' => 'go-dash',
            'target_path' => '/dashboard',
            'created_by_id' => $requester->id,
        ]);
    }

    #[Test]
    public function decline_does_not_create_alias(): void
    {
        $requester = User::factory()->create();
        $admin = User::factory()->admin()->create();

        $request = app(PageAliasService::class)->request($requester, 'no-dash', '/dashboard');

        app(UserRequestDecisionService::class)->decline($request, $admin);

        $this->assertDatabaseMissing('page_aliases', ['alias' => 'no-dash']);
        $this->assertSame(UserRequestStatus::Declined, $request->fresh()->status);
    }

    #[Test]
    public function duplicate_pending_alias_is_rejected(): void
    {
        $requester = User::factory()->create();
        User::factory()->admin()->create();
        $service = app(PageAliasService::class);

        $service->request($requester, 'dup-alias', '/dashboard');

        $this->expectException(ValidationException::class);
        $service->request(User::factory()->create(), 'dup-alias', '/features');
    }

    #[Test]
    public function pending_cap_is_enforced_for_requester(): void
    {
        Config::set('page_aliases.max_pending_requests_per_user', 1);

        $requester = User::factory()->create();
        $service = app(PageAliasService::class);

        $service->request($requester, 'first-alias', '/dashboard');

        $this->expectException(ValidationException::class);
        $service->request($requester, 'second-alias', '/features');
    }

    #[Test]
    public function admin_creates_alias_directly_without_request(): void
    {
        $admin = User::factory()->admin()->create();

        $alias = app(PageAliasService::class)->create($admin, 'admin-link', '/dashboard');

        $this->assertInstanceOf(PageAlias::class, $alias);
        $this->assertDatabaseMissing('user_requests', [
            'type' => UserRequestType::PageAlias->value,
            'requester_id' => $admin->id,
        ]);
    }
}
