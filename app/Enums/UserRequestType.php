<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRequestType: string
{
    case OrganizationInvite = 'organization_invite';
    case OrganizationJoinRequest = 'organization_join_request';
    case ActivityInvite = 'activity_invite';
    case EventOrganizerFlag = 'event_organizer_flag';

    public function label(): string
    {
        return match ($this) {
            self::OrganizationInvite => __('ui.user_requests.received_organization_invite'),
            self::OrganizationJoinRequest => __('ui.user_requests.received_organization_join'),
            self::ActivityInvite => __('ui.user_requests.received_activity_invite'),
            self::EventOrganizerFlag => __('ui.user_requests.received_organizer_flag'),
        };
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
