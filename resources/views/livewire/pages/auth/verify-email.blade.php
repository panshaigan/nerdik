<?php

use App\Livewire\Actions\Logout;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Layout;
use Livewire\Volt\Component;

new #[Layout('layouts.guest')] class extends Component
{
    /**
     * Send an email verification notification to the user.
     */
    public function sendVerification(): void
    {
        $user = Auth::user();

        if ($user === null) {
            return;
        }

        if ($user->hasVerifiedEmail()) {
            $this->redirectIntended(default: route('dashboard', absolute: false), navigate: true);

            return;
        }

        $this->ensureVerificationResendIsNotRateLimited($user->id);

        RateLimiter::hit($this->verificationResendThrottleKey($user->id));

        $user->sendEmailVerificationNotification();

        Session::flash('status', 'verification-link-sent');
    }

    /**
     * @throws ValidationException
     */
    protected function ensureVerificationResendIsNotRateLimited(int $userId): void
    {
        $key = $this->verificationResendThrottleKey($userId);

        if (! RateLimiter::tooManyAttempts($key, 6)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'verificationResend' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function verificationResendThrottleKey(int $userId): string
    {
        return 'verification-resend:'.$userId;
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
        {{ __('ui.auth.verify_intro') }}
    </div>

    @if (session('status') == 'verification-link-sent')
        <div class="mb-4 text-sm font-medium text-success">
            {{ __('ui.auth.verification_link_sent') }}
        </div>
    @endif

    @error('verificationResend')
        <div class="mb-4 text-sm font-medium text-error" role="alert">{{ $message }}</div>
    @enderror

    <div class="mt-4 flex items-center justify-between gap-3">
        <x-button id="ui-auth-verify-resend" class="btn-primary ui-action ui-action-resend" wire:click="sendVerification" data-ui="auth-verify-resend">
            {{ __('ui.auth.resend_verification_email') }}
        </x-button>

        <x-button id="ui-auth-verify-logout" wire:click="logout" type="button" class="btn-link link link-primary h-auto min-h-0 p-0 text-sm font-normal ui-action ui-action-logout" data-ui="auth-verify-logout">
            {{ __('ui.nav.log_out') }}
        </x-button>
    </div>
</div>
