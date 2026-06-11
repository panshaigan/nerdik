<?php

use App\Enums\NotificationPreferenceKey;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    /**
     * @var array<string, array{in_app: bool, email: bool, every_join?: bool}>
     */
    public array $preferences = [];

    public function mount(): void
    {
        $this->preferences = Auth::user()->resolvedNotificationPreferences();
    }

    /**
     * @return array<string, mixed>
     */
    private function preferenceValidationRules(): array
    {
        $rules = [];
        foreach (array_keys(NotificationPreferenceKey::defaultMatrix()) as $key) {
            $rules[sprintf('preferences.%s.in_app', $key)] = ['required', 'boolean'];
            $rules[sprintf('preferences.%s.email', $key)] = ['required', 'boolean'];
        }

        $rules['preferences.'.NotificationPreferenceKey::ActivityParticipantJoined->value.'.every_join'] = ['required', 'boolean'];

        return $rules;
    }

    public function updateNotificationSettings(): void
    {
        $this->validate($this->preferenceValidationRules());

        $defaults = NotificationPreferenceKey::defaultMatrix();
        $joinKey = NotificationPreferenceKey::ActivityParticipantJoined->value;
        /** @var array<string, array{in_app: bool, email: bool, every_join?: bool}> $clean */
        $clean = [];
        foreach (array_keys($defaults) as $key) {
            $block = [
                'in_app' => (bool) ($this->preferences[$key]['in_app'] ?? $defaults[$key]['in_app'] ?? true),
                'email' => (bool) ($this->preferences[$key]['email'] ?? $defaults[$key]['email'] ?? true),
            ];
            if ($key === $joinKey) {
                $block['every_join'] = (bool) ($this->preferences[$key]['every_join'] ?? $defaults[$key]['every_join'] ?? false);
            }
            $clean[(string) $key] = $block;
        }

        $user = Auth::user();
        $profile = $user->profile()->firstOrCreate();
        $profile->notification_preferences = $clean;
        $profile->save();
        $user->setRelation('profile', $profile);

        $this->preferences = $user->resolvedNotificationPreferences();
        $this->dispatch('profile-notifications-updated');
    }
}; ?>

<section id="ui-profile-notifications-section" class="ui-profile-section ui-profile-notifications" data-ui="profile-notifications-section">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.profile.notifications.title') }}
        </h2>
        <p class="mt-1 text-sm text-base-content/70">
            {{ __('ui.profile.notifications.intro') }}
        </p>
    </header>

    <form id="ui-profile-notifications-form" wire:submit="updateNotificationSettings" class="ui-form ui-form-profile-notifications mt-6 space-y-8" data-ui="profile-notifications-form">
        @foreach (\App\Enums\NotificationPreferenceKey::uiSections() as $section)
            <div class="space-y-3">
                <h3 class="font-medium text-base-content">{{ __('ui.profile.notifications.'.$section['group_key']) }}</h3>
                <div class="overflow-x-auto rounded-lg border border-base-300 bg-base-200/40">
                    <table class="table table-sm">
                        <thead class="[&_tr]:border-base-300">
                            <tr>
                                <th class="text-base-content">{{ __('ui.profile.notifications.col_kind') }}</th>
                                <th class="text-center text-base-content">{{ __('ui.profile.notifications.every_join_short') }}</th>
                                <th class="text-center text-base-content">{{ __('ui.profile.notifications.in_app_short') }}</th>
                                <th class="text-center text-base-content">{{ __('ui.profile.notifications.email_short') }}</th>
                            </tr>
                        </thead>
                        <tbody class="[&_tr]:border-base-300">
                            @foreach ($section['keys'] as $preferenceKeyEnum)
                                @php
                                    $pkey = $preferenceKeyEnum->value;
                                @endphp
                                <tr wire:key="{{ $pkey }}">
                                    <td class="max-w-xl text-sm text-base-content">{{ __('ui.profile.notifications.keys.'.$pkey) }}</td>
                                    <td class="text-center">
                                        @if ($pkey === \App\Enums\NotificationPreferenceKey::ActivityParticipantJoined->value)
                                            <input
                                                type="checkbox"
                                                wire:model="preferences.{{ $pkey }}.every_join"
                                                class="checkbox checkbox-sm ui-field ui-field-notification-every-join"
                                                aria-label="{{ __('ui.profile.notifications.every_join_short') }}"
                                            />
                                        @else
                                            <span class="text-base-content/40" aria-hidden="true">—</span>
                                        @endif
                                    </td>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            wire:model="preferences.{{ $pkey }}.in_app"
                                            class="checkbox checkbox-sm ui-field ui-field-notification-in-app"
                                            aria-label="{{ __('ui.profile.notifications.in_app_short') }}"
                                        />
                                    </td>
                                    <td class="text-center">
                                        <input
                                            type="checkbox"
                                            wire:model="preferences.{{ $pkey }}.email"
                                            class="checkbox checkbox-sm ui-field ui-field-notification-email"
                                            aria-label="{{ __('ui.profile.notifications.email_short') }}"
                                        />
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endforeach

        <div class="flex items-center justify-end gap-4">
            <x-action-message class="me-3" on="profile-notifications-updated">
                {{ __('ui.common.saved') }}
            </x-action-message>
            <x-button id="ui-profile-notifications-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="profile-notifications-submit">{{ __('ui.common.save') }}</x-button>
        </div>
    </form>
</section>
