<?php

declare(strict_types=1);

namespace App\Support\AdminEmail;

final readonly class AdminEmailRecipientCriteria
{
    public function __construct(
        public bool $verifiedOnly = true,
        public bool $excludeAdmins = true,
        public ?int $organizationId = null,
    ) {}
}
