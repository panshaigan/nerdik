<?php

namespace App\Livewire\Activities;

use App\Enums\UserRequestType;
use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\Group;
use App\Models\Organization;
use App\Models\Tag;
use App\Models\User;
use App\Models\UserPlayArea;
use App\Services\ContactVisibilityService;
use App\Services\UserHostBanService;
use App\Support\Profile\ProviderContactUrls;
use App\Support\Users\UserReputationStats;
use Illuminate\Http\RedirectResponse;
use Livewire\Component;
use STS\FilamentImpersonate\Facades\Impersonation;

class UserContactPopover extends Component
{
    public ?int $contextActivityId = null;

    public ?int $contextOrganizationId = null;

    public int $targetUserId;

    public function banUser(UserHostBanService $hostBanService): void
    {
        [$viewer, $targetUser] = $this->banActorAndTarget();
        $hostBanService->ban($viewer, $targetUser);
    }

    public function unbanUser(UserHostBanService $hostBanService): void
    {
        [$viewer, $targetUser] = $this->banActorAndTarget();
        $hostBanService->unban($viewer, $targetUser);
    }

    /**
     * @return array{0: User, 1: User}
     */
    private function banActorAndTarget(): array
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            abort(403);
        }

        $targetUser = User::query()->whereKey($this->targetUserId)->first();
        if (! $targetUser instanceof User) {
            abort(404);
        }

        return [$viewer, $targetUser];
    }

    /**
     * Start impersonating the target user (admins only).
     */
    public function impersonate(): RedirectResponse|bool
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User || ! $viewer->canImpersonate() || Impersonation::isImpersonating()) {
            abort(403);
        }

        $targetUser = User::query()->whereKey($this->targetUserId)->first();
        if (! $targetUser instanceof User || ! $targetUser->canBeImpersonated() || $viewer->is($targetUser)) {
            abort(403);
        }

        if (! Impersonation::enter($viewer, $targetUser, config('auth.defaults.guard'))) {
            return false;
        }

        return redirect()->to(route('dashboard'));
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function participationStatsByType(int $userId): array
    {
        return ActivityUser::query()
            ->selectRaw('activity_types.slug as type_slug, count(*) as total')
            ->join('activities', 'activities.id', '=', 'activity_user.activity_id')
            ->leftJoin('activity_types', 'activity_types.id', '=', 'activities.activity_type_id')
            ->where('activity_user.user_id', $userId)
            ->whereNull('activity_user.deleted_at')
            ->whereNull('activities.deleted_at')
            ->groupBy('activity_types.slug')
            ->orderByRaw('count(*) desc')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->type_slug ? __('ui.activities.types.'.$row->type_slug) : __('ui.common.none'),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array<int, array{label: string, count: int}>
     */
    private function hostedStatsByType(int $userId): array
    {
        return Activity::query()
            ->selectRaw('activity_types.slug as type_slug, count(*) as total')
            ->leftJoin('activity_types', 'activity_types.id', '=', 'activities.activity_type_id')
            ->where('activities.created_by', $userId)
            ->whereNull('activities.deleted_at')
            ->groupBy('activity_types.slug')
            ->orderByRaw('count(*) desc')
            ->get()
            ->map(fn ($row): array => [
                'label' => $row->type_slug ? __('ui.activities.types.'.$row->type_slug) : __('ui.common.none'),
                'count' => (int) $row->total,
            ])
            ->all();
    }

    /**
     * @return array{
     *     email: ?array{address: string, mailto: string, gmail: string},
     *     facebook: ?array{profileUrl: string, messagesUrl: ?string, messengerUrl: ?string},
     *     discord: ?array{webUrl: string, appUrl: string},
     * }
     */
    private function resolveContactSections(User $targetUser, bool $canViewContact): array
    {
        $empty = [
            'email' => null,
            'facebook' => null,
            'discord' => null,
        ];

        if (! $canViewContact) {
            return $empty;
        }

        $profile = $targetUser->profile;
        $contactUrls = app(ProviderContactUrls::class);

        $emailAddress = null;
        if ((bool) ($profile?->show_contact_email ?? false) && filled($targetUser->email)) {
            $emailAddress = (string) $targetUser->email;
        } elseif ((bool) ($profile?->show_contact_google ?? true) && filled($profile?->google_email)) {
            $emailAddress = (string) $profile?->google_email;
        }

        $email = $emailAddress !== null
            ? [
                'address' => $emailAddress,
                'mailto' => 'mailto:'.$emailAddress,
                'gmail' => 'https://mail.google.com/mail/?view=cm&to='.rawurlencode($emailAddress),
            ]
            : null;

        $facebook = $profile !== null
            && (bool) ($profile->show_contact_facebook ?? true)
            && filled($profile->facebook_profile_url)
            ? $contactUrls->facebook($profile)
            : null;

        $discord = $profile !== null
            && (bool) ($profile->show_contact_discord ?? true)
            ? $contactUrls->discord($profile)
            : null;

        return [
            'email' => $email,
            'facebook' => $facebook,
            'discord' => $discord,
        ];
    }

    /**
     * @return array{
     *     canViewContact: bool,
     *     canImpersonateTarget: bool,
     *     targetUser: ?User,
     *     hostedStatsByType: array<int, array{label: string, count: int}>,
     *     participationStatsByType: array<int, array{label: string, count: int}>,
     *     contacts: array{
     *         email: ?array{address: string, mailto: string, gmail: string},
     *         facebook: ?array{profileUrl: string, messagesUrl: ?string, messengerUrl: ?string},
     *         discord: ?array{webUrl: string, appUrl: string},
     *     },
     * }
     */
    private function resolveViewData(ContactVisibilityService $contactVisibility): array
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            return $this->emptyState();
        }

        $targetUser = User::query()
            ->with([
                'profile',
                'organization',
                'playArea.city.translations',
                'interestTags.translations',
                'interestTags.tagCategory.translations',
            ])
            ->whereKey($this->targetUserId)
            ->first();

        if (! $targetUser instanceof User) {
            return $this->emptyState();
        }

        $canViewContact = $contactVisibility->canViewContactInfo($viewer, $targetUser);
        $canImpersonateTarget = $viewer->canImpersonate()
            && $targetUser->canBeImpersonated()
            && ! $viewer->is($targetUser)
            && ! Impersonation::isImpersonating();

        $hostBanService = app(UserHostBanService::class);
        $canBanTarget = ! $viewer->is($targetUser) && $hostBanService->viewerCanBan($viewer);
        $isBannedByViewer = $canBanTarget && $hostBanService->isBannedByHost($targetUser, $viewer);
        $reputation = app(UserReputationStats::class)->forUser($targetUser);

        return [
            'canViewContact' => $canViewContact,
            'canImpersonateTarget' => $canImpersonateTarget,
            'canBanTarget' => $canBanTarget,
            'isBannedByViewer' => $isBannedByViewer,
            'targetUser' => $targetUser,
            'hostedStatsByType' => $this->hostedStatsByType($targetUser->id),
            'participationStatsByType' => $this->participationStatsByType($targetUser->id),
            'hostBanCount' => $reputation['ban_count'],
            'absenceCount' => $reputation['absence_count'],
            'contacts' => $this->resolveContactSections($targetUser, $canViewContact),
            'activityInviteOptions' => $this->resolveActivityInviteOptions($viewer, $targetUser),
            'organizationInviteOptions' => $this->resolveOrganizationInviteOptions($viewer, $targetUser),
            'groupInviteOptions' => $this->resolveGroupInviteOptions($viewer, $targetUser),
            'organizationJoinSubjectId' => $this->resolveOrganizationJoinSubjectId($viewer, $targetUser),
            'organizationJoinRecipientId' => $this->resolveOrganizationJoinRecipientId($viewer, $targetUser),
            'playAreaPreview' => $this->resolvePlayAreaPreview($targetUser),
            'interestTagBadges' => $this->resolveInterestTagBadges($targetUser),
        ];
    }

    public function openInvite(string $kind, int $subjectId): void
    {
        match ($kind) {
            'activity' => $this->openActivityInvite($subjectId),
            'organization' => $this->openOrganizationInvite($subjectId),
            'group' => $this->openGroupInvite($subjectId),
            default => abort(404),
        };
    }

    public function openActivityInvite(int $activityId): void
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            abort(403);
        }

        $targetUser = User::query()->whereKey($this->targetUserId)->first();
        if (! $targetUser instanceof User) {
            abort(404);
        }

        $allowedIds = collect($this->resolveActivityInviteOptions($viewer, $targetUser))
            ->pluck('id')
            ->all();

        if (! in_array($activityId, $allowedIds, true)) {
            abort(403);
        }

        $this->dispatch(
            'open-send-user-request',
            type: UserRequestType::ActivityInvite->value,
            subjectId: $activityId,
        );
    }

    public function openOrganizationInvite(int $organizationId): void
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            abort(403);
        }

        $targetUser = User::query()->whereKey($this->targetUserId)->first();
        if (! $targetUser instanceof User) {
            abort(404);
        }

        $allowedIds = collect($this->resolveOrganizationInviteOptions($viewer, $targetUser))
            ->pluck('id')
            ->all();

        if (! in_array($organizationId, $allowedIds, true)) {
            abort(403);
        }

        $this->dispatch(
            'open-send-user-request',
            type: UserRequestType::OrganizationInvite->value,
            subjectId: $organizationId,
        );
    }

    public function openGroupInvite(int $groupId): void
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            abort(403);
        }

        $targetUser = User::query()->whereKey($this->targetUserId)->first();
        if (! $targetUser instanceof User) {
            abort(404);
        }

        $allowedIds = collect($this->resolveGroupInviteOptions($viewer, $targetUser))
            ->pluck('id')
            ->all();

        if (! in_array($groupId, $allowedIds, true)) {
            abort(403);
        }

        $this->dispatch(
            'open-send-user-request',
            type: UserRequestType::GroupInvite->value,
            subjectId: $groupId,
        );
    }

    /**
     * @return null|array{latitude: float, longitude: float, radius_km: int, label: ?string, caption: string}
     */
    private function resolvePlayAreaPreview(User $targetUser): ?array
    {
        $area = $targetUser->playArea;
        if (! $area instanceof UserPlayArea) {
            return null;
        }

        $locationLine = $area->locationLine();
        $caption = $locationLine !== null
            ? (string) __('ui.profile.availability.play_area_preview_caption', [
                'label' => $locationLine,
                'radius' => $area->radius_km,
            ])
            : (string) __('ui.profile.availability.play_area_preview_caption_no_label', [
                'radius' => $area->radius_km,
            ]);

        return [
            'latitude' => (float) $area->latitude,
            'longitude' => (float) $area->longitude,
            'radius_km' => (int) $area->radius_km,
            'label' => $area->label,
            'caption' => $caption,
        ];
    }

    /**
     * @return list<array{id: int, label: string, category: string, badge: string}>
     */
    private function resolveInterestTagBadges(User $targetUser): array
    {
        $locale = app()->getLocale();

        return $targetUser->interestTags
            ->sortBy(fn (Tag $tag) => sprintf('%02d-%s', (int) $tag->tag_category_id, mb_strtolower($tag->displayLabel($locale))))
            ->map(function (Tag $tag) use ($locale): array {
                $category = $tag->tagCategory?->name($locale) ?? '';
                $label = $tag->displayLabel($locale);
                $badge = $category !== '' ? $category.' · '.$label : $label;

                return [
                    'id' => (int) $tag->id,
                    'label' => $label,
                    'category' => $category,
                    'badge' => $badge,
                ];
            })
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function resolveActivityInviteOptions(User $viewer, User $targetUser): array
    {
        if ($viewer->is($targetUser)) {
            return [];
        }

        if ($this->contextActivityId !== null) {
            $activity = Activity::query()->find($this->contextActivityId);
            if ($activity instanceof Activity
                && (int) $activity->created_by === (int) $viewer->id
                && $this->activityAllowsInviteTo($activity, $targetUser)) {
                return [[
                    'id' => (int) $activity->id,
                    'name' => (string) $activity->name,
                ]];
            }
        }

        $startsAtSql = Activity::scheduleStartsAtSql();

        return Activity::query()
            ->where('created_by', $viewer->id)
            ->whereNull('cancelled_at')
            ->whereIn('hosting_mode', [
                Activity::HOSTING_MODE_SELF_HOSTED,
                Activity::HOSTING_MODE_SCHEDULED_ON_EVENT,
            ])
            ->whereRaw("{$startsAtSql} > ?", [now()])
            ->whereDoesntHave('participants', fn ($query) => $query->where('user_id', $targetUser->id))
            ->whereDoesntHave('waitlist', fn ($query) => $query->where('user_id', $targetUser->id))
            ->orderByRaw("{$startsAtSql} ASC")
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Activity $activity): array => [
                'id' => (int) $activity->id,
                'name' => (string) $activity->name,
            ])
            ->values()
            ->all();
    }

    private function activityAllowsInviteTo(Activity $activity, User $targetUser): bool
    {
        if ($activity->isCancelled() || ! $activity->isJoinableMode()) {
            return false;
        }

        $startsAt = $activity->scheduleStartsAt();
        if ($startsAt === null || $startsAt->isPast()) {
            return false;
        }

        if ($activity->participants()->where('user_id', $targetUser->id)->exists()) {
            return false;
        }

        return ! $activity->waitlist()->where('user_id', $targetUser->id)->exists();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function resolveOrganizationInviteOptions(User $viewer, User $targetUser): array
    {
        if ($this->contextOrganizationId !== null) {
            $organization = Organization::query()->find($this->contextOrganizationId);
            if ($organization instanceof Organization
                && $viewer->canModifyEntity($organization)
                && (int) $targetUser->organization_id !== (int) $organization->id) {
                return [[
                    'id' => (int) $organization->id,
                    'name' => (string) $organization->name,
                ]];
            }
        }

        $query = Organization::query()
            ->where('created_by', $viewer->id)
            ->orderBy('name');

        if ($targetUser->organization_id !== null) {
            $query->whereKeyNot($targetUser->organization_id);
        }

        return $query
            ->get(['id', 'name'])
            ->map(fn (Organization $organization): array => [
                'id' => (int) $organization->id,
                'name' => (string) $organization->name,
            ])
            ->values()
            ->all();
    }

    /**
     * @return list<array{id: int, name: string}>
     */
    private function resolveGroupInviteOptions(User $viewer, User $targetUser): array
    {
        if ($viewer->is($targetUser)) {
            return [];
        }

        return Group::query()
            ->where('created_by', $viewer->id)
            ->whereDoesntHave('users', fn ($query) => $query->whereKey($targetUser->id))
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Group $group): array => [
                'id' => (int) $group->id,
                'name' => (string) $group->name,
            ])
            ->values()
            ->all();
    }

    private function resolveOrganizationJoinSubjectId(User $viewer, User $targetUser): ?int
    {
        $organization = $targetUser->organization;
        if (! $organization instanceof Organization) {
            return null;
        }

        if ((int) $viewer->organization_id === (int) $organization->id) {
            return null;
        }

        if ($viewer->canModifyEntity($organization)) {
            return null;
        }

        return $organization->id;
    }

    private function resolveOrganizationJoinRecipientId(User $viewer, User $targetUser): ?int
    {
        $organizationId = $this->resolveOrganizationJoinSubjectId($viewer, $targetUser);
        if ($organizationId === null) {
            return null;
        }

        $ownerId = Organization::query()->whereKey($organizationId)->value('created_by');

        return $ownerId !== null ? (int) $ownerId : null;
    }

    /**
     * @return array{
     *     canViewContact: bool,
     *     canImpersonateTarget: bool,
     *     targetUser: ?User,
     *     hostedStatsByType: array<int, array{label: string, count: int}>,
     *     participationStatsByType: array<int, array{label: string, count: int}>,
     *     contacts: array{
     *         email: ?array{address: string, mailto: string, gmail: string},
     *         facebook: ?array{profileUrl: string, messagesUrl: ?string, messengerUrl: ?string},
     *         discord: ?array{webUrl: string, appUrl: string},
     *     },
     * }
     */
    private function emptyState(): array
    {
        return [
            'canViewContact' => false,
            'canImpersonateTarget' => false,
            'canBanTarget' => false,
            'isBannedByViewer' => false,
            'targetUser' => null,
            'hostedStatsByType' => [],
            'participationStatsByType' => [],
            'hostBanCount' => 0,
            'absenceCount' => 0,
            'contacts' => [
                'email' => null,
                'facebook' => null,
                'discord' => null,
            ],
            'activityInviteOptions' => [],
            'organizationInviteOptions' => [],
            'groupInviteOptions' => [],
            'organizationJoinSubjectId' => null,
            'organizationJoinRecipientId' => null,
            'playAreaPreview' => null,
            'interestTagBadges' => [],
        ];
    }

    public function render()
    {
        return view('livewire.activities.user-contact-popover', $this->resolveViewData(
            app(ContactVisibilityService::class),
        ));
    }
}
