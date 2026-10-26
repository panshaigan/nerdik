<?php

declare(strict_types=1);

namespace Tests\Unit\Services\UserRequests;

use App\Contracts\UserRequests\UserRequestHandler;
use App\Enums\UserRequestType;
use App\Services\UserRequests\Handlers\ActivityInviteRequestHandler;
use App\Services\UserRequests\Handlers\EventOrganizerFlagRequestHandler;
use App\Services\UserRequests\Handlers\OrganizationInviteRequestHandler;
use App\Services\UserRequests\Handlers\OrganizationJoinRequestHandler;
use App\Services\UserRequests\UserRequestHandlerRegistry;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionClass;
use Tests\TestCase;

final class UserRequestHandlerRegistryTest extends TestCase
{
    /**
     * @return array<string, array{0: UserRequestType, 1: class-string<UserRequestHandler>}>
     */
    public static function registeredHandlerProvider(): array
    {
        return [
            'organization invite' => [UserRequestType::OrganizationInvite, OrganizationInviteRequestHandler::class],
            'organization join' => [UserRequestType::OrganizationJoinRequest, OrganizationJoinRequestHandler::class],
            'activity invite' => [UserRequestType::ActivityInvite, ActivityInviteRequestHandler::class],
            'event organizer flag' => [UserRequestType::EventOrganizerFlag, EventOrganizerFlagRequestHandler::class],
        ];
    }

    #[DataProvider('registeredHandlerProvider')]
    public function test_get_returns_handler_for_each_user_request_type(
        UserRequestType $type,
        string $expectedHandlerClass,
    ): void {
        $registry = app(UserRequestHandlerRegistry::class);

        $handler = $registry->get($type);

        $this->assertInstanceOf($expectedHandlerClass, $handler);
        $this->assertSame($type, $handler->type());
    }

    public function test_every_user_request_type_enum_case_is_registered(): void
    {
        $registry = app(UserRequestHandlerRegistry::class);

        foreach (UserRequestType::cases() as $type) {
            $handler = $registry->get($type);
            $this->assertSame($type, $handler->type());
        }
    }

    public function test_get_throws_when_handler_was_removed_from_registry(): void
    {
        $registry = app(UserRequestHandlerRegistry::class);

        $reflection = new ReflectionClass($registry);
        $handlersProperty = $reflection->getProperty('handlers');
        $handlersProperty->setAccessible(true);
        /** @var array<string, UserRequestHandler> $handlers */
        $handlers = $handlersProperty->getValue($registry);
        unset($handlers[UserRequestType::ActivityInvite->value]);
        $handlersProperty->setValue($registry, $handlers);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('No handler registered for request type [activity_invite].');

        $registry->get(UserRequestType::ActivityInvite);
    }
}
