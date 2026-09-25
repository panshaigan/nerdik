<?php

use App\Enums\NotificationPreferenceKey;
use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
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
        $this->reportProfileTabValidation('notifications', function (): void {
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
        });
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

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

    <form id="ui-profile-notifications-form" wire:submit="updateNotificationSettings" novalidate class="ui-form ui-form-profile-notifications mt-6 space-y-8" data-ui="profile-notifications-form">
        @foreach (\App\Enums\NotificationPreferenceKey::uiSections() as $section)
            <div class="space-y-3">
                <h3 class="font-medium text-base-content">{{ __('ui.profile.notifications.'.$section['group_key']) }}</h3>
                <div class="overflow-hidden rounded-lg border border-base-300 bg-base-200/40">
                    <div class="hidden border-b border-base-300 px-4 py-3 text-sm font-medium text-base-content md:grid md:grid-cols-[minmax(0,1fr)_5rem_5rem] md:items-center md:gap-4">
                        <span>{{ __('ui.profile.notifications.col_kind') }}</span>
                        <span class="text-center">{{ __('ui.profile.notifications.in_app_short') }}</span>
                        <span class="text-center">{{ __('ui.profile.notifications.email_short') }}</span>
                    </div>
                    <div class="divide-y divide-base-300">
                        @foreach ($section['keys'] as $preferenceKeyEnum)
                            @php
                                $pkey = $preferenceKeyEnum->value;
                            @endphp
                            <div wire:key="{{ $pkey }}" class="flex flex-col gap-3 p-4 md:grid md:grid-cols-[minmax(0,1fr)_5rem_5rem] md:items-center md:gap-4">
                                <p class="text-sm text-base-content">{{ __('ui.profile.notifications.keys.'.$pkey) }}</p>
                                <div class="flex items-center gap-6 md:contents">
                                    <label class="flex items-center gap-2 md:justify-center">
                                        <span class="text-xs text-base-content/70 md:sr-only">{{ __('ui.profile.notifications.in_app_short') }}</span>
                                        <input
                                            type="checkbox"
                                            wire:model="preferences.{{ $pkey }}.in_app"
                                            class="checkbox checkbox-sm ui-field ui-field-notification-in-app"
                                            aria-label="{{ __('ui.profile.notifications.in_app_short') }}"
                                        />
                                    </label>
                                    <label class="flex items-center gap-2 md:justify-center">
                                        <span class="text-xs text-base-content/70 md:sr-only">{{ __('ui.profile.notifications.email_short') }}</span>
                                        <input
                                            type="checkbox"
                                            wire:model="preferences.{{ $pkey }}.email"
                                            class="checkbox checkbox-sm ui-field ui-field-notification-email"
                                            aria-label="{{ __('ui.profile.notifications.email_short') }}"
                                        />
                                    </label>
                                </div>
                            </div>
                        @endforeach
                    </div>
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
