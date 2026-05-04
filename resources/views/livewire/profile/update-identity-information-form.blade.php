<?php

use App\Models\Organization;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    private const ORGANIZATION_SUGGESTIONS_LIMIT = 100;

    public string $name = '';

    public string $nickname = '';

    public ?int $organization_id = null;

    public string $organization_name = '';

    public string $timezone = '';

    public function mount(): void
    {
        $user = Auth::user();
        $this->name = $user->name ?? '';
        $this->nickname = $user->nickname ?? '';
        $this->organization_id = $user->organization_id;
        $this->organization_name = (string) ($user->organization?->name ?? '');
        $this->timezone = $user->profile?->timezone ?? '';
    }

    public function updateIdentityInformation(): void
    {
        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'max:255'],
            'organization_id' => ['nullable', 'integer', 'exists:organizations,id'],
            'organization_name' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone'],
        ]);

        $user = Auth::user();
        $user->fill([
            'name' => $validated['name'],
            'nickname' => $validated['nickname'],
        ]);
        $user->organization_id = $this->resolveOrganizationIdFromRequest(
            $validated['organization_id'] ?? null,
            $validated['organization_name'] ?? null
        );
        $user->save();

        $profile = $user->profile()->firstOrCreate();
        $profile->timezone = $validated['timezone'] ?: null;
        $profile->save();

        $this->dispatch('profile-identity-updated');
    }

    /**
     * @return list<array{id:int,name:string}>
     */
    protected function organizationSuggestionsForCurrentUser(): array
    {
        $user = Auth::user();
        if (! $user) {
            return [];
        }

        return Organization::query()
            ->where(function ($query) use ($user) {
                $query
                    ->where('created_by', $user->id)
                    ->orWhereHas('events', fn ($events) => $events->where('created_by', $user->id))
                    ->orWhere('id', $user->organization_id);
            })
            ->orderBy('name')
            ->limit(self::ORGANIZATION_SUGGESTIONS_LIMIT)
            ->get(['id', 'name'])
            ->map(fn (Organization $organization) => ['id' => $organization->id, 'name' => $organization->name])
            ->values()
            ->all();
    }

    public function with(): array
    {
        return [
            'organizationSuggestions' => $this->organizationSuggestionsForCurrentUser(),
        ];
    }

    protected function resolveOrganizationIdFromName(?string $organizationName): ?int
    {
        $name = trim((string) $organizationName);

        if ($name === '') {
            return null;
        }

        $organization = Organization::query()
            ->whereRaw('LOWER(name) = ?', [mb_strtolower($name)])
            ->first();

        if ($organization === null) {
            $organization = Organization::create([
                'name' => $name,
            ]);
        }

        return $organization->id;
    }

    protected function resolveOrganizationIdFromRequest(mixed $organizationId, ?string $organizationName): ?int
    {
        if ($organizationId !== null && $organizationId !== '') {
            $id = (int) $organizationId;
            if (Organization::query()->whereKey($id)->exists()) {
                return $id;
            }
        }

        return $this->resolveOrganizationIdFromName($organizationName);
    }
}; ?>

<section id="ui-profile-identity-section" class="ui-profile-section ui-profile-identity" data-ui="profile-identity-section">
    <form id="ui-profile-identity-form" wire:submit="updateIdentityInformation" class="ui-form ui-form-profile-identity space-y-4" data-ui="profile-identity-form">
        <x-input wire:model="nickname" label="{{ __('Nickname') }}" type="text" name="nickname" error-field="nickname" required />
        <x-input wire:model="name" label="{{ __('Name (optional)') }}" type="text" name="name" error-field="name" />

        <div class="relative">
            <input type="hidden" wire:model="organization_id" data-profile-org-id />
            <x-input
                wire:model.live.debounce.300ms="organization_name"
                label="{{ __('Organization (optional)') }}"
                type="text"
                name="organization_name"
                error-field="organization_name"
                autocomplete="off"
                data-profile-org-input
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="profile-org-suggestions-popup"
            />
            <div id="profile-org-suggestions-popup" class="absolute inset-x-0 top-full z-20 mt-1 hidden max-h-56 w-full min-w-0 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg" data-profile-org-popup wire:ignore role="listbox"></div>
            <x-field-error :messages="$errors->get('organization_id')" class="mt-2" />
        </div>

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

@push('scripts')
<script>
(() => {
    let profileIdentityOrgAbort;
    function initProfileIdentityOrgInput() {
        profileIdentityOrgAbort?.abort();
        profileIdentityOrgAbort = new AbortController();
        const signal = profileIdentityOrgAbort.signal;

        const orgInput = document.querySelector('#ui-profile-identity-form [data-profile-org-input]');
        const orgPopup = document.querySelector('#ui-profile-identity-form [data-profile-org-popup]');
        const orgIdInput = document.querySelector('#ui-profile-identity-form [data-profile-org-id]');
        if (!orgInput || !orgPopup || !orgIdInput) {
            return;
        }
        const orgSuggestions = @json($organizationSuggestions ?? []);
        let orgShown = [];
        let orgActive = -1;
        let selectedOrg = null;

        function closePopup() {
            orgPopup.classList.add('hidden');
            orgPopup.innerHTML = '';
            orgActive = -1;
        }

        function chooseOrg(item) {
            orgInput.value = item.name;
            orgInput.dispatchEvent(new Event('input', { bubbles: true }));
            orgIdInput.value = String(item.id);
            orgIdInput.dispatchEvent(new Event('input', { bubbles: true }));
            selectedOrg = { id: item.id, name: item.name };
            closePopup();
        }

        function render(items) {
            orgShown = items.slice(0, 8);
            orgPopup.innerHTML = '';
            orgActive = -1;
            if (orgShown.length === 0) {
                closePopup();
                return;
            }
            const frag = document.createDocumentFragment();
            orgShown.forEach((item, idx) => {
                const btn = document.createElement('button');
                btn.type = 'button';
                btn.className = 'block w-full px-3 py-2 text-left text-sm hover:bg-base-200';
                btn.textContent = item.name;
                btn.dataset.orgSuggestionIdx = String(idx);
                btn.addEventListener('mousedown', (event) => event.preventDefault());
                btn.addEventListener('click', () => chooseOrg(item));
                frag.appendChild(btn);
            });
            orgPopup.appendChild(frag);
            orgPopup.classList.remove('hidden');
        }

        function updateFromInput() {
            const q = orgInput.value.trim().toLowerCase();
            if (selectedOrg && q !== selectedOrg.name.toLowerCase()) {
                orgIdInput.value = '';
                orgIdInput.dispatchEvent(new Event('input', { bubbles: true }));
                selectedOrg = null;
            }
            if (q.length < 1) {
                render(orgSuggestions.slice(0, 8));
                return;
            }
            render(orgSuggestions.filter((item) => item.name.toLowerCase().includes(q) && item.name.toLowerCase() !== q));
        }

        orgInput.addEventListener('input', updateFromInput, { signal });
        orgInput.addEventListener('focus', updateFromInput, { signal });
    }

    document.addEventListener('livewire:navigating', () => profileIdentityOrgAbort?.abort());
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileIdentityOrgInput, { once: true });
    } else {
        initProfileIdentityOrgInput();
    }
    document.addEventListener('livewire:navigated', initProfileIdentityOrgInput);
})();
</script>
@endpush
