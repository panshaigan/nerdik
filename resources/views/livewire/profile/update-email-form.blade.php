<?php

use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Livewire\Profile\Concerns\WithAdvancedPasswordConfirmation;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Js;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;
    use WithAdvancedPasswordConfirmation;

    public string $email = '';

    public string $new_email = '';

    public ?string $pending_email = null;

    public function mount(): void
    {
        $user = Auth::user();
        $this->email = $user->email;
        $this->pending_email = $user->pending_email;
    }

    public function requestEmailChange(): void
    {
        $this->validate([
            'new_email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class, 'email'),
                Rule::unique(User::class, 'pending_email'),
                'different:email',
            ],
        ]);

        $this->openPasswordConfirmation('confirmEmailChange');
    }

    public function confirmEmailChange(string $password): void
    {
        $this->passwordConfirmationPassword = $password;

        $this->reportProfileTabValidation('advanced', function (): void {
            try {
                $validated = $this->validate([
                    'new_email' => [
                        'required',
                        'string',
                        'lowercase',
                        'email',
                        'max:255',
                        Rule::unique(User::class, 'email'),
                        Rule::unique(User::class, 'pending_email'),
                        'different:email',
                    ],
                    'passwordConfirmationPassword' => ['required', 'string', 'current_password'],
                ]);
            } catch (ValidationException $e) {
                $this->reset('passwordConfirmationPassword');

                throw $e;
            }

            $user = Auth::user();
            $user->forceFill([
                'pending_email' => $validated['new_email'],
            ])->save();

            $this->pending_email = $user->pending_email;
            $this->reset('new_email', 'passwordConfirmationPassword');

            $user->sendPendingEmailVerificationNotification();

            $this->toastEmailChangeLinkSent((string) $user->pending_email);
        });
    }

    public function resendPendingEmailVerification(): void
    {
        $user = Auth::user();

        if ($user === null || ! $user->hasPendingEmailChange()) {
            return;
        }

        $this->ensureEmailChangeResendIsNotRateLimited($user->id);

        RateLimiter::hit($this->emailChangeResendThrottleKey($user->id));

        $user->sendPendingEmailVerificationNotification();

        $this->toastEmailChangeLinkSent((string) $user->pending_email);
    }

    public function cancelPendingEmailChange(): void
    {
        $user = Auth::user();

        if ($user === null || ! $user->hasPendingEmailChange()) {
            return;
        }

        $user->forceFill([
            'pending_email' => null,
        ])->save();

        $this->pending_email = null;
    }

    protected function toastEmailChangeLinkSent(string $email): void
    {
        $this->js('window.toast('.Js::from([
            'toast' => [
                'type' => 'success',
                'title' => __('ui.profile.email_change_link_sent', ['email' => $email]),
                'description' => '',
                'icon' => '',
                'css' => 'alert-success',
                'timeout' => 4000,
                'noProgress' => false,
            ],
        ]).')');
    }

    /**
     * @throws ValidationException
     */
    protected function ensureEmailChangeResendIsNotRateLimited(int $userId): void
    {
        $key = $this->emailChangeResendThrottleKey($userId);

        if (! RateLimiter::tooManyAttempts($key, 6)) {
            return;
        }

        $seconds = RateLimiter::availableIn($key);

        throw ValidationException::withMessages([
            'emailChangeResend' => trans('auth.throttle', [
                'seconds' => $seconds,
                'minutes' => ceil($seconds / 60),
            ]),
        ]);
    }

    protected function emailChangeResendThrottleKey(int $userId): string
    {
        return 'email-change-resend:'.$userId;
    }
}; ?>

<section id="ui-profile-email-section" class="ui-profile-section ui-profile-email" data-ui="profile-email-section">
    <header>
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.profile.update_email_title') }}
        </h2>

        <p class="mt-1 text-sm text-base-content/70">
            {{ __('ui.profile.update_email_hint') }}
        </p>
    </header>

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4 mt-6" />

    @if ($pending_email)
        <div class="mt-6 space-y-4 rounded-lg border border-base-200 bg-base-200/40 p-6" data-ui="profile-pending-email">
            <p class="text-sm text-base-content/80">
                {{ __('ui.profile.pending_email_notice', ['email' => $pending_email]) }}
            </p>

            @error('emailChangeResend')
                <p class="text-sm font-medium text-error" role="alert">{{ $message }}</p>
            @enderror

            <div class="flex flex-wrap items-center gap-3">
                <x-button
                    type="button"
                    class="btn-primary ui-action ui-action-resend"
                    wire:click="resendPendingEmailVerification"
                    data-ui="profile-email-resend"
                >
                    {{ __('ui.profile.pending_email_resend') }}
                </x-button>

                <x-button
                    type="button"
                    class="btn-ghost ui-action ui-action-cancel"
                    wire:click="cancelPendingEmailChange"
                    data-ui="profile-email-cancel-pending"
                >
                    {{ __('ui.profile.cancel_pending_email') }}
                </x-button>
            </div>
        </div>
    @else
        <form id="ui-profile-email-form" wire:submit="requestEmailChange" novalidate class="ui-form ui-form-profile-email mt-6 space-y-4" data-ui="profile-email-form">
            <x-input
                wire:model="email"
                label="{{ __('ui.profile.current_email') }}"
                placeholder="{{ __('ui.common.email') }}"
                type="email"
                name="email"
                error-field="email"
                readonly
                disabled
                inline
            />

            <x-input
                wire:model="new_email"
                label="{{ __('ui.profile.new_email') }}"
                placeholder="{{ __('ui.profile.new_email') }}"
                type="email"
                name="new_email"
                error-field="new_email"
                autocomplete="email"
                required
                class="ui-field ui-field-new-email"
                data-ui="profile-email-new-email"
                inline
            />

            <div class="flex items-center justify-end gap-4">
                <x-button id="ui-profile-email-submit" class="btn-primary ui-action ui-action-submit" type="submit" data-ui="profile-email-submit">
                    {{ __('ui.profile.request_email_change') }}
                </x-button>
            </div>
        </form>
    @endif

    <x-modal
        wire:model="confirmingPassword"
        :title="__('ui.profile.confirm_password_modal_title')"
        :subtitle="__('ui.profile.confirm_password_modal_hint')"
        class="backdrop-blur ui-modal ui-modal-password-confirm"
        box-class="ui-modal-surface"
        separator
        data-ui="profile-email-password-modal"
    >
        <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.fix_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

        <form wire:submit.prevent="runPasswordConfirmation" class="space-y-4" data-ui="profile-email-password-modal-form">
            <x-password
                wire:model="passwordConfirmationPassword"
                label="{{ __('ui.profile.current_password') }}"
                placeholder="{{ __('ui.profile.current_password') }}"
                name="passwordConfirmationPassword"
                error-field="passwordConfirmationPassword"
                autocomplete="current-password"
                class="ui-field ui-field-current-password"
                data-ui="profile-email-password-modal-input"
            />

            <div class="modal-action">
                <x-button type="button" class="btn-ghost" wire:click="cancelPasswordConfirmation" data-ui="profile-email-password-modal-cancel">
                    {{ __('ui.common.cancel') }}
                </x-button>
                <x-button type="submit" class="btn-primary" data-ui="profile-email-password-modal-confirm">
                    {{ __('ui.profile.confirm_password_continue') }}
                </x-button>
            </div>
        </form>
    </x-modal>
</section>

@push('styles')
<style>
[data-ui="profile-email-new-email"] ~ .label .text-error {
    display: none;
}
</style>
@endpush
