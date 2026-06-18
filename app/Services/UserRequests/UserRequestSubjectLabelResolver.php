<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\Organization;
use App\Models\UserRequest;

class UserRequestSubjectLabelResolver
{
    public function resolve(UserRequest $request): string
    {
        $subject = $request->subject;

        return match ($request->type) {
            UserRequestType::OrganizationInvite,
            UserRequestType::OrganizationJoinRequest => $subject instanceof Organization
                ? $subject->name
                : __('ui.common.unknown_user'),
            UserRequestType::ActivityInvite => $subject instanceof Activity
                ? $subject->name
                : __('ui.common.unknown_user'),
            UserRequestType::EventOrganizerFlag => __('ui.user_requests.organizer_flag_subject'),
        };
    }
}
