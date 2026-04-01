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

<section>
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('Notification settings') }}
        </h2>
        <p class="mt-1 text-sm text-base-content/70">
            {{ __('Choose whether to receive email for these events. You will always see in-app notifications.') }}
        </p>
    </header>

    <form wire:submit="updateNotificationSettings" class="mt-6 space-y-4">
        <label class="flex cursor-pointer items-center gap-2">
            <input type="checkbox" wire:model="notify_email_proposal_updates" class="checkbox checkbox-sm" />
            <span class="text-sm text-base-content">{{ __('Email when a proposal of mine is accepted or rejected') }}</span>
        </label>
        <label class="flex cursor-pointer items-center gap-2">
            <input type="checkbox" wire:model="notify_email_waitlist_promoted" class="checkbox checkbox-sm" />
            <span class="text-sm text-base-content">{{ __('Email when I am moved from a waitlist to a participant') }}</span>
        </label>
        <div class="flex items-center gap-4">
            <x-button class="btn-primary" type="submit">{{ __('Save') }}</x-button>
            <x-action-message class="me-3" on="saved">
                {{ __('Saved.') }}
            </x-action-message>
        </div>
    </form>
</section>
