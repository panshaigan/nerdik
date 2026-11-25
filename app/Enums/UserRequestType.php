<?php

declare(strict_types=1);

namespace App\Enums;

enum UserRequestType: string
{
    case OrganizationInvite = 'organization_invite';
    case OrganizationJoinRequest = 'organization_join_request';
    case ActivityInvite = 'activity_invite';
    case EventOrganizerFlag = 'event_organizer_flag';
    case GroupInvite = 'group_invite';
    case PageAlias = 'page_alias';

    public function label(): string
    {
        return match ($this) {
            self::OrganizationInvite => __('ui.user_requests.received_organization_invite'),
            self::OrganizationJoinRequest => __('ui.user_requests.received_organization_join'),
            self::ActivityInvite => __('ui.user_requests.received_activity_invite'),
            self::EventOrganizerFlag => __('ui.user_requests.received_organizer_flag'),
            self::GroupInvite => __('ui.user_requests.received_group_invite'),
            self::PageAlias => __('ui.user_requests.received_page_alias'),
        };
    }

    public function isAdminQueue(): bool
    {
        return match ($this) {
            self::EventOrganizerFlag, self::PageAlias => true,
            default => false,
        };
    }

    /**
     * @return list<self>
     */
    public static function adminQueueTypes(): array
    {
        return array_values(array_filter(
            self::cases(),
            static fn (self $type): bool => $type->isAdminQueue(),
        ));
    }

    /**
     * @return list<string>
     */
    public static function adminQueueTypeValues(): array
    {
        return array_map(
            static fn (self $type): string => $type->value,
            self::adminQueueTypes(),
        );
    }

    /** @return list<string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
