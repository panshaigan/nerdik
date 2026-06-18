<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Contracts\UserRequests\UserRequestHandler;
use App\Enums\UserRequestType;
use Illuminate\Contracts\Container\Container;

class UserRequestHandlerRegistry
{
    /** @var array<string, UserRequestHandler> */
    private array $handlers = [];

    public function __construct(Container $container)
    {
        $handlerClasses = [
            Handlers\OrganizationInviteRequestHandler::class,
            Handlers\OrganizationJoinRequestHandler::class,
            Handlers\ActivityInviteRequestHandler::class,
            Handlers\EventOrganizerFlagRequestHandler::class,
        ];

        foreach ($handlerClasses as $class) {
            $handler = $container->make($class);
            $this->handlers[$handler->type()->value] = $handler;
        }
    }

    public function get(UserRequestType $type): UserRequestHandler
    {
        return $this->handlers[$type->value]
            ?? throw new \InvalidArgumentException("No handler registered for request type [{$type->value}].");
    }
}
