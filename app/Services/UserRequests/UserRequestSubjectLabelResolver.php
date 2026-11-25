<?php

declare(strict_types=1);

namespace App\Services\UserRequests;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\Group;
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
            UserRequestType::GroupInvite => $subject instanceof Group
                ? $subject->name
                : __('ui.common.unknown_user'),
            UserRequestType::EventOrganizerFlag => __('ui.user_requests.organizer_flag_subject'),
            UserRequestType::PageAlias => $this->pageAliasSubjectLabel($request),
        };
    }

    private function pageAliasSubjectLabel(UserRequest $request): string
    {
        /** @var array<string, mixed>|null $payload */
        $payload = $request->payload;
        $alias = is_array($payload) ? ($payload['alias'] ?? null) : null;
        $targetPath = is_array($payload) ? ($payload['target_path'] ?? null) : null;

        if (! is_string($alias) || $alias === '' || ! is_string($targetPath) || $targetPath === '') {
            return __('ui.page_aliases.unknown_subject');
        }

        return __('ui.page_aliases.request_subject', [
            'alias' => $alias,
            'target' => $targetPath,
        ]);
    }
}
