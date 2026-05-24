<?php

use App\Models\Organization;
use App\Models\UserProfile;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rule;
use Livewire\Volt\Component;

new class extends Component
{
    private const ORGANIZATION_SUGGESTIONS_LIMIT = 100;

    public string $name = '';

    public string $nickname = '';

    public string $email = '';

    public string $discord_handle = '';

    public string $timezone = '';

    public string $avatar_bg_color = '#1d4ed8';

    public string $avatar_text_color = '#ffffff';

    public ?int $organization_id = null;

    public string $organization_name = '';

    /**
     * Mount the component.
     */
    public function mount(): void
    {
        $u = Auth::user();
        $this->name = $u->name ?? '';
        $this->nickname = $u->nickname ?? '';
        $this->email = $u->email;
        $this->discord_handle = $u->profile?->discord_handle ?? '';
        $this->timezone = $u->profile?->timezone ?? '';
        $this->avatar_bg_color = $u->profile?->avatar_bg_color ?? '#1d4ed8';
        $this->avatar_text_color = $u->profile?->avatar_text_color ?? '#ffffff';
        $this->organization_id = $u->organization_id;
        $this->organization_name = (string) ($u->organization?->name ?? '');
    }

    /**
     * Update the profile information for the currently authenticated user.
     */
    public function updateProfileInformation(): void
    {
        $user = Auth::user();

        $validated = $this->validate([
            'name' => ['nullable', 'string', 'max:255'],
            'nickname' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($user->id)],
            'discord_handle' => ['nullable', 'string', 'max:255'],
            'timezone' => ['nullable', 'string', 'timezone'],
            'avatar_bg_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'avatar_text_color' => ['required', 'string', 'regex:/^#[0-9A-Fa-f]{6}$/'],
            'organization_id' => ['nullable', 'integer', Rule::exists(Organization::class, 'id')->withoutTrashed()],
            'organization_name' => ['nullable', 'string', 'max:255'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'nickname' => $validated['nickname'],
            'email' => $validated['email'],
        ]);
        $user->organization_id = $this->resolveOrganizationIdFromRequest(
            $validated['organization_id'] ?? null,
            $validated['organization_name'] ?? null
        );

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        $profile = $user->profile()->firstOrCreate();
        $profile->fill([
            'discord_handle' => $validated['discord_handle'] ?: null,
            'timezone' => $validated['timezone'] ?: null,
            'avatar_bg_color' => $validated['avatar_bg_color'],
            'avatar_text_color' => $validated['avatar_text_color'],
        ]);
        $profile->save();
        $user->setRelation('profile', $profile);

        $this->dispatch('profile-updated', name: $user->nickname);
    }

    /**
     * Send an email verification notification to the current user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false));

            return;
        }

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
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
}; ?>

<section id="ui-profile-information-section" class="ui-profile-section ui-profile-information" data-ui="profile-information-section">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Profile Information') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __("Update your account's profile information and email address.") }}
        </p>
    </header>

    <form id="ui-profile-information-form" wire:submit="updateProfileInformation" class="ui-form ui-form-profile-information mt-6 space-y-4" data-ui="profile-information-form">
        <x-input
            wire:model="nickname"
            label="{{ __('Nickname') }}"
            type="text"
            name="nickname"
            error-field="nickname"
            required
            autofocus
            autocomplete="nickname"
            class="ui-field ui-field-nickname"
            data-ui="profile-nickname"
        />

        <x-input
            wire:model="name"
            label="{{ __('Name (optional)') }}"
            type="text"
            name="name"
            error-field="name"
            autocomplete="name"
            class="ui-field ui-field-name"
            data-ui="profile-name"
        />

        <x-input
            wire:model="discord_handle"
            label="{{ __('Discord (optional)') }}"
            type="text"
            name="discord_handle"
            placeholder="username"
            error-field="discord_handle"
            autocomplete="off"
            class="ui-field ui-field-discord"
            data-ui="profile-discord"
        />

        <div class="relative">
            <input type="hidden" wire:model="organization_id" data-profile-org-id />
            <x-input
                wire:model.live.debounce.300ms="organization_name"
                label="{{ __('Organization (optional)') }}"
                type="text"
                name="organization_name"
                placeholder="{{ __('Organization') }}"
                error-field="organization_name"
                autocomplete="off"
                class="ui-field ui-field-organization"
                data-ui="profile-organization"
                data-profile-org-input
                aria-autocomplete="list"
                aria-expanded="false"
                aria-controls="profile-org-suggestions-popup"
            />
            <div id="profile-org-suggestions-popup"
                class="absolute inset-x-0 top-full z-20 mt-1 hidden max-h-56 w-full min-w-0 overflow-y-auto rounded-lg border border-base-300 bg-base-100 py-1 shadow-lg"
                data-profile-org-popup
                wire:ignore
                role="listbox"></div>
            <x-field-error :messages="$errors->get('organization_id')" class="mt-2" />
            <x-field-error :messages="$errors->get('organization_name')" class="mt-2" />
        </div>

        <div>
            <x-colorpicker
                wire:model.live="avatar_bg_color"
                label="{{ __('Avatar background color') }}"
                name="avatar_bg_color"
                error-field="avatar_bg_color"
                required
                class="ui-field ui-field-avatar-bg-color"
                data-ui="profile-avatar-bg-color"
            />
        </div>

        <div>
            <x-colorpicker
                wire:model.live="avatar_text_color"
                label="{{ __('Avatar text color') }}"
                name="avatar_text_color"
                error-field="avatar_text_color"
                required
                class="ui-field ui-field-avatar-text-color"
                data-ui="profile-avatar-text-color"
            />
        </div>

        <div>
            <fieldset class="fieldset py-0">
                <legend class="fieldset-legend mb-0.5">{{ __('Timezone (for displaying dates)') }}</legend>
                <select wire:model="timezone" id="timezone" name="timezone" class="select select-bordered w-full ui-field ui-field-timezone" data-ui="profile-timezone">
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
            <p class="mt-1 text-xs text-base-content/60">{{ __('All times are stored in UTC; your timezone only affects how they are shown.') }}</p>
            <x-field-error :messages="$errors->get('timezone')" class="mt-2" />
        </div>

        <div>
            <x-input
                wire:model="email"
                label="{{ __('Email') }}"
                type="email"
                name="email"
                error-field="email"
                required
                autocomplete="username"
                class="ui-field ui-field-email"
                data-ui="profile-email"
                readonly
                disabled
            />

            @if (auth()->user() instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && ! auth()->user()->hasVerifiedEmail())
                <div class="mt-2">
                    <p class="text-sm text-base-content/80">
                        {{ __('Your email address is unverified.') }}

                        <x-button type="button" wire:click.prevent="sendVerification" class="btn-link link link-primary h-auto min-h-0 p-0 text-sm font-normal">
                            {{ __('Click here to re-send the verification email.') }}
                        </x-button>
                    </p>

                    @if (session('status') === 'verification-link-sent')
                        <p class="mt-2 text-sm font-medium text-success">
                            {{ __('A new verification link has been sent to your email address.') }}
                        </p>
                    @endif
                </div>
            @endif
        </div>

        <div class="flex items-center gap-4">
            <x-button id="ui-profile-information-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="profile-information-submit">{{ __('Save') }}</x-button>

            <x-action-message class="me-3" on="profile-updated">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>

@push('scripts')
<script>
(() => {
    let profileOrgScriptsAbort;
    function initProfileOrgInput() {
        profileOrgScriptsAbort?.abort();
        profileOrgScriptsAbort = new AbortController();
        const signal = profileOrgScriptsAbort.signal;

        const orgInput = document.querySelector('[data-profile-org-input]');
        const orgPopup = document.querySelector('[data-profile-org-popup]');
        const orgIdInput = document.querySelector('[data-profile-org-id]');
        if (!orgInput || !orgPopup || !orgIdInput) {
            return;
        }

        const orgScope = orgPopup.parentElement;
        const orgSuggestions = @json($organizationSuggestions ?? []);
        let orgShown = [];
        let orgActive = -1;
        let selectedOrg = null;
        const oid = orgIdInput.value.trim();
        const oname = orgInput.value.trim();
        if (oid !== '' && oname !== '') {
            selectedOrg = { id: parseInt(oid, 10), name: oname };
        }

        function syncSelectionFromInput() {
            const text = orgInput.value.trim();
            if (selectedOrg && text.toLowerCase() !== selectedOrg.name.toLowerCase()) {
                orgIdInput.value = '';
                orgIdInput.dispatchEvent(new Event('input', { bubbles: true }));
                selectedOrg = null;
            }
        }

        function closePopup() {
            orgPopup.classList.add('hidden');
            orgPopup.innerHTML = '';
            orgActive = -1;
            orgInput.setAttribute('aria-expanded', 'false');
        }

        function openPopup() {
            if (orgShown.length === 0) {
                closePopup();
                return;
            }
            orgPopup.classList.remove('hidden');
            orgInput.setAttribute('aria-expanded', 'true');
        }

        function applyActive() {
            [...orgPopup.querySelectorAll('[data-org-suggestion-idx]')].forEach((el, idx) => {
                const isActive = idx === orgActive;
                el.classList.toggle('bg-base-200', isActive);
                el.setAttribute('aria-selected', isActive ? 'true' : 'false');
            });
        }

        function chooseOrg(item) {
            orgInput.value = item.name;
            orgInput.dispatchEvent(new Event('input', { bubbles: true }));
            orgIdInput.value = String(item.id);
            orgIdInput.dispatchEvent(new Event('input', { bubbles: true }));
            selectedOrg = { id: item.id, name: item.name };
            closePopup();
        }

        function renderOrg(items) {
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
                btn.className = 'block w-full cursor-pointer px-3 py-2 text-left text-sm hover:bg-base-200';
                btn.textContent = item.name;
                btn.dataset.orgSuggestionIdx = String(idx);
                btn.setAttribute('role', 'option');
                btn.setAttribute('aria-selected', 'false');
                btn.addEventListener('mousedown', (e) => e.preventDefault());
                btn.addEventListener('click', () => chooseOrg(item));
                frag.appendChild(btn);
            });
            orgPopup.appendChild(frag);
            openPopup();
        }

        function updateOrgFromInput() {
            syncSelectionFromInput();
            const q = orgInput.value.trim().toLowerCase();
            if (q.length < 1) {
                renderOrg(orgSuggestions.slice(0, 8));
                return;
            }

            const items = orgSuggestions.filter(
                (o) => o.name.toLowerCase().includes(q) && o.name.toLowerCase() !== q
            );
            renderOrg(items);
        }

        orgInput.addEventListener('input', updateOrgFromInput, { signal });
        orgInput.addEventListener('focus', updateOrgFromInput, { signal });
        orgInput.addEventListener('click', updateOrgFromInput, { signal });
        orgInput.addEventListener('keydown', (e) => {
            if (orgPopup.classList.contains('hidden') || orgShown.length === 0) return;

            if (e.key === 'ArrowDown') {
                e.preventDefault();
                orgActive = (orgActive + 1) % orgShown.length;
                applyActive();
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                orgActive = orgActive <= 0 ? orgShown.length - 1 : orgActive - 1;
                applyActive();
            } else if (e.key === 'Enter') {
                e.preventDefault();
                if (orgActive >= 0 && orgActive < orgShown.length) {
                    chooseOrg(orgShown[orgActive]);
                }
            } else if (e.key === 'Escape') {
                closePopup();
            }
        }, { signal });

        document.addEventListener('click', (e) => {
            if (orgScope && !orgScope.contains(e.target)) {
                closePopup();
            }
        }, { signal });
    }

    document.addEventListener('livewire:navigating', () => {
        profileOrgScriptsAbort?.abort();
    });
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initProfileOrgInput, { once: true });
    } else {
        initProfileOrgInput();
    }
    document.addEventListener('livewire:navigated', initProfileOrgInput);
})();
</script>
@endpush
