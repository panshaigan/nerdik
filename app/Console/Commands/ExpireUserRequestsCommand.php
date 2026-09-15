<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\UserRequests\UserRequestExpirer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user-requests:expire')]
#[Description('Expire pending user requests past their expiration time')]
class ExpireUserRequestsCommand extends Command
{
    public function __construct(
        private readonly UserRequestExpirer $expirer,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->expirer->expireDue();

        $this->info("Expired {$count} user request(s).");

        return self::SUCCESS;
    }
}
