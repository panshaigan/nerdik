<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestStatus;
use App\Models\UserRequest;

class UserRequestExpirer
{
    public function __construct(
        private readonly UserRequestNotifier $notifier,
    ) {}

    public function expireDue(): int
    {
        $count = 0;
        $respondedAt = now();

        UserRequest::query()
            ->expiredBefore($respondedAt)
            ->with(['requester', 'recipient', 'subject'])
            ->orderBy('id')
            ->chunkById(100, function ($requests) use (&$count, $respondedAt): void {
                foreach ($requests as $request) {
                    $request->update([
                        'status' => UserRequestStatus::Expired,
                        'responded_at' => $respondedAt,
                    ]);

                    $this->notifier->notifyResolved($request);
                    $count++;
                }
            });

        return $count;
    }
}
