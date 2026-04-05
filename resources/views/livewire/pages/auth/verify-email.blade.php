<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        if (Auth::user()->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        Auth::user()->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * Log the current user out of the application.
     */
    public function logout(Logout $logout): void
    {
        $logout();

        $this->redirect('/', navigate: true);
    }
}; ?>

<div id="ui-auth-verify-root" class="ui-auth ui-auth-verify" data-ui="auth-verify-root">
    <div class="mb-4 text-sm text-base-content/80">
        {{ __('Thanks for signing up! Before getting started, could you verify your email address by clicking on the link we just emailed to you? If you didn\'t receive the email, we will gladly send you another.') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-success">
            {{ __('A new verification link has been sent to the email address you provided during registration.') }}
        </div>
    @endif

    <div class="mt-4 flex items-center justify-between gap-3">
        <x-button id="ui-auth-verify-resend" class="btn-primary ui-action ui-action-resend" wire:click="sendVerification" data-ui="auth-verify-resend">
            {{ __('Resend Verification Email') }}
        </x-button>

        <x-button id="ui-auth-verify-logout" wire:click="logout" type="button" class="btn-link link link-primary h-auto min-h-0 p-0 text-sm font-normal ui-action ui-action-logout" data-ui="auth-verify-logout">
            {{ __('Log Out') }}
        </x-button>
    </div>
</div>
