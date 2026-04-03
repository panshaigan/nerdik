<?php

use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    public bool $notify_email_proposal_updates = true;
    public bool $notify_email_waitlist_promoted = true;

    public function mount(): void
    {
        $u = Auth::user();
        $this->notify_email_proposal_updates = (bool) ($u->notify_email_proposal_updates ?? true);
        $this->notify_email_waitlist_promoted = (bool) ($u->notify_email_waitlist_promoted ?? true);
    }

    public function updateNotificationSettings(): void
    {
        $this->validate([
            'notify_email_proposal_updates' => ['boolean'],
            'notify_email_waitlist_promoted' => ['boolean'],
        ]);

        Auth::user()->update([
            'notify_email_proposal_updates' => $this->notify_email_proposal_updates,
            'notify_email_waitlist_promoted' => $this->notify_email_waitlist_promoted,
        ]);

        $this->dispatch('saved');
    }
}; ?>

<section id="ui-profile-notifications-section" class="ui-profile-section ui-profile-notifications" data-ui="profile-notifications-section">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Notification settings') }}
        </h2>
        <p class="mt-1 text-sm text-base-content/70">
            {{ __('Choose whether to receive email for these events. You will always see in-app notifications.') }}
        </p>
    </header>

    <form id="ui-profile-notifications-form" wire:submit="updateNotificationSettings" class="ui-form ui-form-profile-notifications mt-6 space-y-4" data-ui="profile-notifications-form">
        <label class="flex cursor-pointer items-center gap-2 ui-field ui-field-notify-proposal-updates" data-ui="profile-notify-proposal-updates">
            <input type="checkbox" wire:model="notify_email_proposal_updates" class="checkbox checkbox-sm" />
            <span class="text-sm text-base-content">{{ __('Email when a proposal of mine is accepted or rejected') }}</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2 ui-field ui-field-notify-waitlist-promoted" data-ui="profile-notify-waitlist-promoted">
            <input type="checkbox" wire:model="notify_email_waitlist_promoted" class="checkbox checkbox-sm" />
            <span class="text-sm text-base-content">{{ __('Email when I am moved from a waitlist to a participant') }}</span>
        </label>
        <div class="flex items-center gap-4">
            <x-button id="ui-profile-notifications-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="profile-notifications-submit">{{ __('Save') }}</x-button>
            <x-action-message class="me-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
