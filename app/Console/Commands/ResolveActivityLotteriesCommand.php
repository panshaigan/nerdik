<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\ActivityLotteryService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('activities:resolve-lotteries')]
#[Description('Resolve pending activity lotteries whose draw time has passed')]
class ResolveActivityLotteriesCommand extends Command
{
    public function __construct(
        private readonly ActivityLotteryService $lotteryService,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = $this->lotteryService->resolveDueActivities();

        $this->info("Resolved {$count} activity lottery(ies).");

        return self::SUCCESS;
    }
}
