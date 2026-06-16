<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\User;
use App\Services\ContactVisibilityService;
use Livewire\Component;

class UserContactPopover extends Component
{
    public int $targetUserId;

    /**
     * @return array{
     *     canViewContact: bool,
     *     targetUser: ?User,
     *     statsByType: array<int, array{label: string, count: int}>,
     *     hostedActivitiesCount: int,
     *     contacts: array{
     *         email: ?string,
     *         facebook: ?string,
     *         google: ?string,
     *         discord: ?string,
     *     },
     * }
     */
    private function resolveViewData(ContactVisibilityService $contactVisibility): array
    {
        $viewer = auth()->user();
        if (! $viewer instanceof User) {
            return $this->emptyState();
        }

        $targetUser = User::query()->with('profile', 'organization')->whereKey($this->targetUserId)->first();

        if (! $targetUser instanceof User) {
            return $this->emptyState();
        }

        $canViewContact = $contactVisibility->canViewContactInfo($viewer, $targetUser);

        $statsByType = ActivityUser::query()
            ->selectRaw('activity_types.slug as type_slug, count(*) as total')
            ->join('activities', 'activities.id', '=', 'activity_user.activity_id')
            ->leftJoin('activity_types', 'activity_types.id', '=', 'activities.activity_type_id')
            ->where('activity_user.user_id', $targetUser->id)
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

        $hostedActivitiesCount = Activity::query()
            ->where('created_by', $targetUser->id)
            ->whereNull('deleted_at')
            ->count();

        $profile = $targetUser->profile;
        $contacts = [
            'email' => ($canViewContact && (bool) ($profile?->show_contact_email ?? false)) ? $targetUser->email : null,
            'facebook' => ($canViewContact && (bool) ($profile?->show_contact_facebook ?? true))
                ? (filled($profile?->facebook_id) ? 'https://www.facebook.com/messages/t/'.rawurlencode((string) $profile?->facebook_id) : null)
                : null,
            'google' => ($canViewContact && (bool) ($profile?->show_contact_google ?? true))
                ? (filled($profile?->google_email) ? (string) $profile?->google_email : null)
                : null,
            'discord' => ($canViewContact && (bool) ($profile?->show_contact_discord ?? true))
                ? (filled($profile?->discord_id) ? 'https://discord.com/users/'.rawurlencode((string) $profile?->discord_id) : null)
                : null,
        ];

        if (filled($contacts['email'])) {
            $contacts['google'] = null;
        }

        return [
            'canViewContact' => $canViewContact,
            'targetUser' => $targetUser,
            'statsByType' => $statsByType,
            'hostedActivitiesCount' => $hostedActivitiesCount,
            'contacts' => $contacts,
        ];
    }

    /**
     * @return array{
     *     canViewContact: bool,
     *     targetUser: ?User,
     *     statsByType: array<int, array{label: string, count: int}>,
     *     hostedActivitiesCount: int,
     *     contacts: array{email: ?string, facebook: ?string, google: ?string, discord: ?string},
     * }
     */
    private function emptyState(): array
    {
        return [
            'canViewContact' => false,
            'targetUser' => null,
            'statsByType' => [],
            'hostedActivitiesCount' => 0,
            'contacts' => [
                'email' => null,
                'facebook' => null,
                'google' => null,
                'discord' => null,
            ],
        ];
    }

    public function render()
    {
        return view('livewire.activities.user-contact-popover', $this->resolveViewData(
            app(ContactVisibilityService::class),
        ));
    }
}
