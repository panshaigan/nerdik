<?php

declare(strict_types=1);

namespace App\Support\Features;

/**
 * Static structure for the public /features catalog page.
 *
 * @phpstan-type FeatureItem array{id: string, icon: string, title_key: string, body_key: string}
 * @phpstan-type FeatureGroup array{group_key: string, items: list<FeatureItem>}
 * @phpstan-type FeatureTab array{tab_key: string, label_key: string, intro_key: string, groups: list<FeatureGroup>}
 */
final class FeatureCatalog
{
    /** @return list<FeatureItem> */
    public static function everyoneItems(): array
    {
        return [
            ['id' => 'everyone_locales', 'icon' => 'o-language', 'title_key' => 'everyone_locales_title', 'body_key' => 'everyone_locales_body'],
            ['id' => 'everyone_theme', 'icon' => 'o-swatch', 'title_key' => 'everyone_theme_title', 'body_key' => 'everyone_theme_body'],
            ['id' => 'everyone_social_login', 'icon' => 'o-user-circle', 'title_key' => 'everyone_social_login_title', 'body_key' => 'everyone_social_login_body'],
            ['id' => 'everyone_public_browse', 'icon' => 'o-eye', 'title_key' => 'everyone_public_browse_title', 'body_key' => 'everyone_public_browse_body'],
            ['id' => 'everyone_share', 'icon' => 'o-share', 'title_key' => 'everyone_share_title', 'body_key' => 'everyone_share_body'],
            ['id' => 'everyone_calendar', 'icon' => 'o-calendar-days', 'title_key' => 'everyone_calendar_title', 'body_key' => 'everyone_calendar_body'],
            ['id' => 'everyone_feedback', 'icon' => 'o-chat-bubble-left-right', 'title_key' => 'everyone_feedback_title', 'body_key' => 'everyone_feedback_body'],
        ];
    }

    /**
     * @return list<string, FeatureTab>
     */
    public static function tabs(): array
    {
        return [
            'players' => [
                'tab_key' => 'players',
                'label_key' => 'tab_players',
                'intro_key' => 'tab_players_intro',
                'groups' => [
                    [
                        'group_key' => 'players_discover',
                        'items' => [
                            ['id' => 'players_search_map_list', 'icon' => 'o-magnifying-glass', 'title_key' => 'players_search_map_list_title', 'body_key' => 'players_search_map_list_body'],
                            ['id' => 'players_filters', 'icon' => 'o-funnel', 'title_key' => 'players_filters_title', 'body_key' => 'players_filters_body'],
                            ['id' => 'players_badges_previews', 'icon' => 'o-sparkles', 'title_key' => 'players_badges_previews_title', 'body_key' => 'players_badges_previews_body'],
                            ['id' => 'players_global_search', 'icon' => 'o-globe-alt', 'title_key' => 'players_global_search_title', 'body_key' => 'players_global_search_body'],
                            ['id' => 'players_catalogs', 'icon' => 'o-rectangle-stack', 'title_key' => 'players_catalogs_title', 'body_key' => 'players_catalogs_body'],
                        ],
                    ],
                    [
                        'group_key' => 'players_take_a_seat',
                        'items' => [
                            ['id' => 'players_open_join', 'icon' => 'o-check-circle', 'title_key' => 'players_open_join_title', 'body_key' => 'players_open_join_body'],
                            ['id' => 'players_waitlist_lottery', 'icon' => 'o-queue-list', 'title_key' => 'players_waitlist_lottery_title', 'body_key' => 'players_waitlist_lottery_body'],
                            ['id' => 'players_overlap_guard', 'icon' => 'o-clock', 'title_key' => 'players_overlap_guard_title', 'body_key' => 'players_overlap_guard_body'],
                            ['id' => 'players_familiarity', 'icon' => 'o-academic-cap', 'title_key' => 'players_familiarity_title', 'body_key' => 'players_familiarity_body'],
                            ['id' => 'players_cancellation_deadline', 'icon' => 'o-x-circle', 'title_key' => 'players_cancellation_deadline_title', 'body_key' => 'players_cancellation_deadline_body'],
                        ],
                    ],
                    [
                        'group_key' => 'players_follow_along',
                        'items' => [
                            ['id' => 'players_interests', 'icon' => 'o-heart', 'title_key' => 'players_interests_title', 'body_key' => 'players_interests_body'],
                            ['id' => 'players_dashboard', 'icon' => 'o-home', 'title_key' => 'players_dashboard_title', 'body_key' => 'players_dashboard_body'],
                            ['id' => 'players_enrollment_reminders', 'icon' => 'o-bell-alert', 'title_key' => 'players_enrollment_reminders_title', 'body_key' => 'players_enrollment_reminders_body'],
                        ],
                    ],
                    [
                        'group_key' => 'players_at_the_table',
                        'items' => [
                            ['id' => 'players_running_late', 'icon' => 'o-exclamation-triangle', 'title_key' => 'players_running_late_title', 'body_key' => 'players_running_late_body'],
                            ['id' => 'players_live_rosters', 'icon' => 'o-arrow-path', 'title_key' => 'players_live_rosters_title', 'body_key' => 'players_live_rosters_body'],
                            ['id' => 'players_contact_popover', 'icon' => 'o-user', 'title_key' => 'players_contact_popover_title', 'body_key' => 'players_contact_popover_body'],
                        ],
                    ],
                    [
                        'group_key' => 'players_inbox',
                        'items' => [
                            ['id' => 'players_invites_requests', 'icon' => 'o-envelope', 'title_key' => 'players_invites_requests_title', 'body_key' => 'players_invites_requests_body'],
                            ['id' => 'players_notifications', 'icon' => 'o-bell', 'title_key' => 'players_notifications_title', 'body_key' => 'players_notifications_body'],
                        ],
                    ],
                    [
                        'group_key' => 'players_account',
                        'items' => [
                            ['id' => 'players_profile_identity', 'icon' => 'o-identification', 'title_key' => 'players_profile_identity_title', 'body_key' => 'players_profile_identity_body'],
                            ['id' => 'players_contact_methods', 'icon' => 'o-at-symbol', 'title_key' => 'players_contact_methods_title', 'body_key' => 'players_contact_methods_body'],
                            ['id' => 'players_avatar_gallery', 'icon' => 'o-photo', 'title_key' => 'players_avatar_gallery_title', 'body_key' => 'players_avatar_gallery_body'],
                            ['id' => 'players_organizer_request', 'icon' => 'o-hand-raised', 'title_key' => 'players_organizer_request_title', 'body_key' => 'players_organizer_request_body'],
                            ['id' => 'players_create_activity', 'icon' => 'o-plus', 'title_key' => 'players_create_activity_title', 'body_key' => 'players_create_activity_body'],
                        ],
                    ],
                    [
                        'group_key' => 'players_trust',
                        'items' => [
                            ['id' => 'players_reputation', 'icon' => 'o-shield-exclamation', 'title_key' => 'players_reputation_title', 'body_key' => 'players_reputation_body'],
                            ['id' => 'players_delete_account', 'icon' => 'o-trash', 'title_key' => 'players_delete_account_title', 'body_key' => 'players_delete_account_body'],
                        ],
                    ],
                ],
            ],
            'hosts' => [
                'tab_key' => 'hosts',
                'label_key' => 'tab_hosts',
                'intro_key' => 'tab_hosts_intro',
                'groups' => [
                    [
                        'group_key' => 'hosts_create_sessions',
                        'items' => [
                            ['id' => 'hosts_activity_types', 'icon' => 'o-puzzle-piece', 'title_key' => 'hosts_activity_types_title', 'body_key' => 'hosts_activity_types_body'],
                            ['id' => 'hosts_hosting_modes', 'icon' => 'o-map', 'title_key' => 'hosts_hosting_modes_title', 'body_key' => 'hosts_hosting_modes_body'],
                            ['id' => 'hosts_self_hosted_place', 'icon' => 'o-map-pin', 'title_key' => 'hosts_self_hosted_place_title', 'body_key' => 'hosts_self_hosted_place_body'],
                            ['id' => 'hosts_duplicate_series', 'icon' => 'o-document-duplicate', 'title_key' => 'hosts_duplicate_series_title', 'body_key' => 'hosts_duplicate_series_body'],
                        ],
                    ],
                    [
                        'group_key' => 'hosts_who_sits',
                        'items' => [
                            ['id' => 'hosts_participation_modes', 'icon' => 'o-user-group', 'title_key' => 'hosts_participation_modes_title', 'body_key' => 'hosts_participation_modes_body'],
                            ['id' => 'hosts_lottery_timing', 'icon' => 'o-ticket', 'title_key' => 'hosts_lottery_timing_title', 'body_key' => 'hosts_lottery_timing_body'],
                            ['id' => 'hosts_capacity_age', 'icon' => 'o-users', 'title_key' => 'hosts_capacity_age_title', 'body_key' => 'hosts_capacity_age_body'],
                            ['id' => 'hosts_spectators', 'icon' => 'o-eye', 'title_key' => 'hosts_spectators_title', 'body_key' => 'hosts_spectators_body'],
                        ],
                    ],
                    [
                        'group_key' => 'hosts_table_setup',
                        'items' => [
                            ['id' => 'hosts_familiarity_survey', 'icon' => 'o-clipboard-document-list', 'title_key' => 'hosts_familiarity_survey_title', 'body_key' => 'hosts_familiarity_survey_body'],
                            ['id' => 'hosts_suggested_bring', 'icon' => 'o-shopping-bag', 'title_key' => 'hosts_suggested_bring_title', 'body_key' => 'hosts_suggested_bring_body'],
                            ['id' => 'hosts_tags_cover', 'icon' => 'o-tag', 'title_key' => 'hosts_tags_cover_title', 'body_key' => 'hosts_tags_cover_body'],
                            ['id' => 'hosts_external_links', 'icon' => 'o-link', 'title_key' => 'hosts_external_links_title', 'body_key' => 'hosts_external_links_body'],
                        ],
                    ],
                    [
                        'group_key' => 'hosts_on_programme',
                        'items' => [
                            ['id' => 'hosts_propose_event', 'icon' => 'o-paper-airplane', 'title_key' => 'hosts_propose_event_title', 'body_key' => 'hosts_propose_event_body'],
                            ['id' => 'hosts_auto_accept', 'icon' => 'o-bolt', 'title_key' => 'hosts_auto_accept_title', 'body_key' => 'hosts_auto_accept_body'],
                        ],
                    ],
                    [
                        'group_key' => 'hosts_invites',
                        'items' => [
                            ['id' => 'hosts_activity_invites', 'icon' => 'o-envelope-open', 'title_key' => 'hosts_activity_invites_title', 'body_key' => 'hosts_activity_invites_body'],
                        ],
                    ],
                    [
                        'group_key' => 'hosts_roster',
                        'items' => [
                            ['id' => 'hosts_waitlist_approve', 'icon' => 'o-hand-thumb-up', 'title_key' => 'hosts_waitlist_approve_title', 'body_key' => 'hosts_waitlist_approve_body'],
                            ['id' => 'hosts_absence_roster', 'icon' => 'o-clipboard-document-check', 'title_key' => 'hosts_absence_roster_title', 'body_key' => 'hosts_absence_roster_body'],
                            ['id' => 'hosts_auto_promote', 'icon' => 'o-arrow-up-circle', 'title_key' => 'hosts_auto_promote_title', 'body_key' => 'hosts_auto_promote_body'],
                            ['id' => 'hosts_roster_pdf', 'icon' => 'o-printer', 'title_key' => 'hosts_roster_pdf_title', 'body_key' => 'hosts_roster_pdf_body'],
                        ],
                    ],
                    [
                        'group_key' => 'hosts_host_tools',
                        'items' => [
                            ['id' => 'hosts_ban', 'icon' => 'o-no-symbol', 'title_key' => 'hosts_ban_title', 'body_key' => 'hosts_ban_body'],
                        ],
                    ],
                    [
                        'group_key' => 'hosts_stay_informed',
                        'items' => [
                            ['id' => 'hosts_host_notifications', 'icon' => 'o-bell', 'title_key' => 'hosts_host_notifications_title', 'body_key' => 'hosts_host_notifications_body'],
                        ],
                    ],
                ],
            ],
            'organizers' => [
                'tab_key' => 'organizers',
                'label_key' => 'tab_organizers',
                'intro_key' => 'tab_organizers_intro',
                'groups' => [
                    [
                        'group_key' => 'organizers_programmes',
                        'items' => [
                            ['id' => 'organizers_create_events', 'icon' => 'o-calendar-days', 'title_key' => 'organizers_create_events_title', 'body_key' => 'organizers_create_events_body'],
                            ['id' => 'organizers_visibility_lifecycle', 'icon' => 'o-lock-closed', 'title_key' => 'organizers_visibility_lifecycle_title', 'body_key' => 'organizers_visibility_lifecycle_body'],
                            ['id' => 'organizers_covers_links', 'icon' => 'o-photo', 'title_key' => 'organizers_covers_links_title', 'body_key' => 'organizers_covers_links_body'],
                        ],
                    ],
                    [
                        'group_key' => 'organizers_team_branding',
                        'items' => [
                            ['id' => 'organizers_maintainers', 'icon' => 'o-user-plus', 'title_key' => 'organizers_maintainers_title', 'body_key' => 'organizers_maintainers_body'],
                            ['id' => 'organizers_organizations', 'icon' => 'o-building-office-2', 'title_key' => 'organizers_organizations_title', 'body_key' => 'organizers_organizations_body'],
                        ],
                    ],
                    [
                        'group_key' => 'organizers_places',
                        'items' => [
                            ['id' => 'organizers_venues_rooms', 'icon' => 'o-map-pin', 'title_key' => 'organizers_venues_rooms_title', 'body_key' => 'organizers_venues_rooms_body'],
                        ],
                    ],
                    [
                        'group_key' => 'organizers_plan',
                        'items' => [
                            ['id' => 'organizers_slots', 'icon' => 'o-table-cells', 'title_key' => 'organizers_slots_title', 'body_key' => 'organizers_slots_body'],
                            ['id' => 'organizers_slot_constraints', 'icon' => 'o-adjustments-horizontal', 'title_key' => 'organizers_slot_constraints_title', 'body_key' => 'organizers_slot_constraints_body'],
                            ['id' => 'organizers_detach_map_series', 'icon' => 'o-map', 'title_key' => 'organizers_detach_map_series_title', 'body_key' => 'organizers_detach_map_series_body'],
                        ],
                    ],
                    [
                        'group_key' => 'organizers_enrollment',
                        'items' => [
                            ['id' => 'organizers_enrollment_windows', 'icon' => 'o-window', 'title_key' => 'organizers_enrollment_windows_title', 'body_key' => 'organizers_enrollment_windows_body'],
                        ],
                    ],
                    [
                        'group_key' => 'organizers_proposals',
                        'items' => [
                            ['id' => 'organizers_proposal_inbox', 'icon' => 'o-inbox', 'title_key' => 'organizers_proposal_inbox_title', 'body_key' => 'organizers_proposal_inbox_body'],
                            ['id' => 'organizers_accept_reject', 'icon' => 'o-check-badge', 'title_key' => 'organizers_accept_reject_title', 'body_key' => 'organizers_accept_reject_body'],
                        ],
                    ],
                    [
                        'group_key' => 'organizers_day_of',
                        'items' => [
                            ['id' => 'organizers_live_rosters', 'icon' => 'o-arrow-path', 'title_key' => 'organizers_live_rosters_title', 'body_key' => 'organizers_live_rosters_body'],
                            ['id' => 'organizers_event_pdf', 'icon' => 'o-printer', 'title_key' => 'organizers_event_pdf_title', 'body_key' => 'organizers_event_pdf_body'],
                            ['id' => 'organizers_running_late', 'icon' => 'o-exclamation-triangle', 'title_key' => 'organizers_running_late_title', 'body_key' => 'organizers_running_late_body'],
                        ],
                    ],
                    [
                        'group_key' => 'organizers_stay_informed',
                        'items' => [
                            ['id' => 'organizers_digests', 'icon' => 'o-bell-alert', 'title_key' => 'organizers_digests_title', 'body_key' => 'organizers_digests_body'],
                        ],
                    ],
                ],
            ],
        ];
    }

    /** @return list<string> */
    public static function validTabKeys(): array
    {
        return array_keys(self::tabs());
    }

    /** @return list<string> */
    public static function allItemIds(): array
    {
        $ids = array_column(self::everyoneItems(), 'id');

        foreach (self::tabs() as $tab) {
            foreach ($tab['groups'] as $group) {
                foreach ($group['items'] as $item) {
                    $ids[] = $item['id'];
                }
            }
        }

        return $ids;
    }

    public static function langKey(string $suffix): string
    {
        return 'ui.features.'.$suffix;
    }
}
