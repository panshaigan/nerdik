<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\UserRequestStatus;
use App\Models\UserRequest;
use App\Services\UserRequests\UserRequestNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('user-requests:expire')]
#[Description('Expire pending user requests past their expiration time')]
class ExpireUserRequestsCommand extends Command
{
    public function __construct(
        private readonly UserRequestNotifier $notifier,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $count = 0;

        UserRequest::query()
            ->expiredBefore(now())
            ->orderBy('id')
            ->chunkById(100, function ($requests) use (&$count): void {
                foreach ($requests as $request) {
                    $request->update([
                        'status' => UserRequestStatus::Expired,
                        'responded_at' => now(),
                    ]);

                    $this->notifier->notifyResolved($request->fresh(['requester', 'recipient', 'subject']));
                    $count++;
                }
            });

        $this->info("Expired {$count} user request(s).");

        return self::SUCCESS;
    }
}
