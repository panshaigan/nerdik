<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public string $name = '';

    public string $nickname = '';

    public ?int $organization_id = null;

    public string $timezone = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name ?? '';
        $this->nickname = $user->nickname ?? '';
        $this->organization_id = $user->organization_id;
        $this->timezone = $user->profile?->timezone ?? '';
    }

    public function updateIdentityInformation(): void
    {
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
    }

    public function with(): array
    {
        $user = Auth::user();

        return [
            'organizationOptions' => $user === null
                ? []
                : Organization::query()
                    ->where('created_by', $user->id)
                    ->orderBy('name')
                    ->get(['id', 'name'])
                    ->map(fn (Organization $organization) => ['id' => $organization->id, 'name' => $organization->name])
                    ->values()
                    ->all(),
        ];
    }
}; ?>

<section id="ui-profile-identity-section" class="ui-profile-section ui-profile-identity" data-ui="profile-identity-section">
    <form id="ui-profile-identity-form" wire:submit="updateIdentityInformation" class="ui-form ui-form-profile-identity space-y-4" data-ui="profile-identity-form">
        <x-input wire:model="nickname" label="{{ __('Nickname') }}" type="text" name="nickname" error-field="nickname" required />
        <x-input wire:model="name" label="{{ __('Name (optional)') }}" type="text" name="name" error-field="name" />

        <div>
            <fieldset class="fieldset py-0">
                <legend class="fieldset-legend mb-0.5">{{ __('Timezone (for displaying dates)') }}</legend>
                <select wire:model="timezone" name="timezone" class="select select-bordered w-full">
                    <option value="">{{ __('Use server default (UTC)') }}</option>
                    @if ($timezone && ! in_array($timezone, ['UTC', 'Europe/Warsaw', 'Europe/London', 'Europe/Berlin', 'Europe/Paris', 'America/New_York', 'America/Chicago', 'America/Los_Angeles', 'Asia/Tokyo', 'Australia/Sydney'], true))
                        <option value="{{ $timezone }}" selected>{{ $timezone }}</option>
                    @endif
                    <option value="UTC">UTC</option>
                    <option value="Europe/Warsaw">Europe/Warsaw</option>
                    <option value="Europe/London">Europe/London</option>
                    <option value="Europe/Berlin">Europe/Berlin</option>
                    <option value="Europe/Paris">Europe/Paris</option>
                    <option value="America/New_York">America/New_York</option>
                    <option value="America/Chicago">America/Chicago</option>
                    <option value="America/Los_Angeles">America/Los_Angeles</option>
                    <option value="Asia/Tokyo">Asia/Tokyo</option>
                    <option value="Australia/Sydney">Australia/Sydney</option>
                </select>
            </fieldset>
            <x-field-error :messages="$errors->get('timezone')" class="mt-2" />
        </div>

        <div class="flex items-center gap-4">
            <x-button class="btn-primary" type="submit">{{ __('Save') }}</x-button>
            <x-action-message class="me-3" on="profile-identity-updated">{{ __('Saved.') }}</x-action-message>
        </div>
    </form>
</section>
