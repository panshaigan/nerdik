<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests\Handlers;

use App\Enums\UserRequestType;
use App\Services\UserRequests\Handlers\PageAliasRequestHandler;
use App\Services\UserRequests\UserRequestHandlerRegistry;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

final class PageAliasRequestHandlerTest extends TestCase
{
    #[Test]
    public function handler_is_registered_for_page_alias_type(): void
    {
        $registry = app(UserRequestHandlerRegistry::class);
        $handler = $registry->get(UserRequestType::PageAlias);

        $this->assertInstanceOf(PageAliasRequestHandler::class, $handler);
        $this->assertSame(UserRequestType::PageAlias, $handler->type());
    }
}
