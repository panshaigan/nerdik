<?php

use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
    public string $name = '';

    public string $nickname = '';

    public ?int $organization_id = null;

    public string $timezone = '';

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
            : '';
        $this->timezoneOptions = profile_timezone_options();
        $this->organizationOptions = Organization::query()
            ->where('created_by', $user->id)
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Organization $organization) => ['id' => $organization->id, 'name' => $organization->name])
            ->values()
            ->all();
    }

    public function updateIdentityInformation(): void
    {
        $this->reportProfileTabValidation('identity', function (): void {
            $validated = $this->validate([
                'name' => ['nullable', 'string', 'max:255'],
                'nickname' => ['required', 'string', 'max:255'],
                'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
                'timezone' => ['nullable', 'string', 'timezone'],
            ]);

            $user = Auth::user();
            $user->fill([
                'name' => $validated['name'],
                'nickname' => $validated['nickname'],
                'organization_id' => $validated['organization_id'] ?? null,
            ]);
            $user->save();

            $profile = $user->profile()->firstOrCreate();
            $profile->timezone = $validated['timezone'] ?: null;
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
                :placeholder="__('ui.profile.timezone_server_default')"
                placeholder-value=""
                error-field="timezone"
                inline
            />
        </div>

        <div class="flex items-center justify-end gap-4">
            <x-action-message class="me-3" on="profile-identity-updated">{{ __('ui.common.saved') }}</x-action-message>
            <x-button class="btn-primary" type="submit">{{ __('ui.common.save') }}</x-button>
        </div>
    </form>
</section>
