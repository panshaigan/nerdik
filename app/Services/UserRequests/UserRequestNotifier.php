<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestStatus;
use App\Enums\UserRequestType;
use App\Models\User;
use App\Models\UserRequest;
use App\Notifications\UserRequestReceivedNotification;
use App\Notifications\UserRequestResolvedNotification;
use Illuminate\Support\Facades\Notification;

class UserRequestNotifier
{
    public function notifyReceived(UserRequest $request): void
    {
        $request->loadMissing(['requester', 'recipient', 'subject']);

        if ($request->type === UserRequestType::EventOrganizerFlag) {
            $admins = User::query()->where('is_admin', true)->get();
            Notification::send($admins, new UserRequestReceivedNotification($request));

            return;
        }

        if ($request->recipient instanceof User) {
            $request->recipient->notify(new UserRequestReceivedNotification($request));
        }
    }

    public function notifyResolved(UserRequest $request, ?string $declineNote = null): void
    {
        $request->loadMissing(['requester', 'recipient', 'respondedBy', 'subject']);

        if ($request->requester instanceof User) {
            $request->requester->notify(new UserRequestResolvedNotification($request, $declineNote));
        }

        if ($request->status === UserRequestStatus::Cancelled && $request->recipient instanceof User) {
            $request->recipient->notify(new UserRequestResolvedNotification($request));
        }

        if ($request->status === UserRequestStatus::Expired) {
            if ($request->recipient instanceof User) {
                $request->recipient->notify(new UserRequestResolvedNotification($request));
            }
        }
    }
}
