<?php

namespace App\Livewire\Activities;

use App\Models\Activity;
use App\Models\ActivityUser;
use App\Models\User;
use App\Services\ContactVisibilityService;
use App\Support\Profile\ProviderContactUrls;
use Livewire\Component;

class UserContactPopover extends Component
{
    public int $targetUserId;

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

        $targetUser = User::query()->with('profile', 'organization')->whereKey($this->targetUserId)->first();

        if (! $targetUser instanceof User) {
            return $this->emptyState();
        }

        $canViewContact = $contactVisibility->canViewContactInfo($viewer, $targetUser);

        return [
            'canViewContact' => $canViewContact,
            'targetUser' => $targetUser,
            'hostedStatsByType' => $this->hostedStatsByType($targetUser->id),
            'participationStatsByType' => $this->participationStatsByType($targetUser->id),
            'contacts' => $this->resolveContactSections($targetUser, $canViewContact),
        ];
    }

    /**
     * @return array{
     *     canViewContact: bool,
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
            'targetUser' => null,
            'hostedStatsByType' => [],
            'participationStatsByType' => [],
            'contacts' => [
                'email' => null,
                'facebook' => null,
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
