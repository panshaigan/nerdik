<?php

use App\Enums\TimeDisplayFormat;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
    public string $name = '';

    public string $nickname = '';

    public ?int $organization_id = null;

    public string $timezone = '';

    public string $time_display_format = '24h';

    public $organizationOptions = null;

    /** @var list<array{id: string, name: string}> */
    public array $timezoneOptions = [];

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name ?? '';
        $this->nickname = $user->nickname ?? '';
        $this->organization_id = $user->organization_id;
        $this->timezone = in_array($user->profile?->timezone, profile_timezone_ids(), true)
            ? (string) $user->profile?->timezone
            : default_profile_timezone();
        $this->time_display_format = $user->time_display_format->value;
        $this->timezoneOptions = profile_timezone_options();
        $this->organizationOptions = Organization::query()
            ->where(function ($query) use ($user): void {
                $query->where('created_by', $user->id);

                if ($user->organization_id !== null) {
                    $query->orWhere('id', $user->organization_id);
                }
            })
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $organization) => ['id' => $organization->id, 'name' => $organization->name])
            ->values()
            ->all();
    }

    public function updateIdentityInformation(): void
    {
        $this->reportProfileTabValidation('identity', function (): void {
            $allowedOrganizationIds = collect($this->organizationOptions)
                ->pluck('id')
                ->all();

            $validated = $this->validate([
                'name' => ['nullable', 'string', 'max:255'],
                'nickname' => ['required', 'string', 'max:255'],
                'organization_id' => [
                    'nullable',
                    'integer',
                    Rule::in($allowedOrganizationIds),
                ],
                'timezone' => ['required', 'string', Rule::in(profile_timezone_ids())],
                'time_display_format' => ['required', 'string', Rule::enum(TimeDisplayFormat::class)],
            ]);

            $user = Auth::user();
            $user->fill([
                'name' => $validated['name'],
                'nickname' => $validated['nickname'],
                'organization_id' => $validated['organization_id'] ?? null,
            ]);
            $user->save();

            $profile = $user->profile()->firstOrCreate();
            $profile->timezone = $validated['timezone'];
            $profile->time_display_format = TimeDisplayFormat::from($validated['time_display_format']);
            $profile->save();

            $this->dispatch('profile-identity-updated');
        });
    }
}; ?>

<section id="ui-profile-identity-section" class="ui-profile-section ui-profile-identity py-6" data-ui="profile-identity-section">
    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />
    <form id="ui-profile-identity-form" wire:submit="updateIdentityInformation" novalidate class="ui-form ui-form-profile-identity space-y-4" data-ui="profile-identity-form">
        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <x-input
                wire:model="nickname"
                label="{{ __('ui.auth.nickname') }}"
                placeholder="{{ __('ui.auth.nickname') }}"
                type="text"
                name="nickname"
                error-field="nickname"
                inline
                required />
            <x-input
                wire:model="name"
                label="{{ __('ui.profile.name_optional') }}"
                placeholder="{{ __('ui.profile.name_optional') }}"
                type="text"
                name="name"
                error-field="name"
                inline
            />
            <x-select
                wire:model="organization_id"
                label="{{ __('ui.profile.organization') }}"
                :options="$organizationOptions"
                :placeholder="__('ui.common.none')"
                placeholder-value=""
                error-field="organization_id"
                inline
                :disabled="count($organizationOptions) === 0"
                :hint="count($organizationOptions) === 0 ? __('ui.organizations.empty') : null"
            />

            <x-select
                wire:model="timezone"
                name="timezone"
                label="{{ __('ui.profile.timezone_label') }}"
                :options="$timezoneOptions"
                error-field="timezone"
                inline
            />

            <x-select
                wire:model="time_display_format"
                name="time_display_format"
                label="{{ __('ui.profile.time_display_format_label') }}"
                :options="TimeDisplayFormat::optionsForSelect()"
                error-field="time_display_format"
                inline
            />
        </div>

        <div class="flex items-center justify-end gap-4">
            <x-action-message class="me-3" on="profile-identity-updated">{{ __('ui.common.saved') }}</x-action-message>
            <x-button class="btn-primary" type="submit">{{ __('ui.common.save') }}</x-button>
        </div>
    </form>

    @if (! auth()->user()->canCreateEvents())
        <div class="mt-8 border-t border-base-300 pt-6" data-ui="profile-organizer-request">
            <livewire:user-requests.send-user-request
                type="event_organizer_flag"
                :key="'profile-organizer-request-'.auth()->id()"
            />
        </div>
    @endif
</section>
