<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\Notifications\Scheduled\ScheduledPeriodicDigestSender;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('notifications:scheduled-digest')]
#[Description('Send scheduled periodic digest notifications')]
class SendScheduledPeriodicNotificationsCommand extends Command
{
    public function __construct(
        private readonly ScheduledPeriodicDigestSender $sender,
    ) {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $sent = $this->sender->sendDueDigests();

        $this->info("Sent {$sent} scheduled digest(s).");

        return self::SUCCESS;
    }
}
