<?php

namespace App\Livewire\Concerns;

use Illuminate\Validation\ValidationException;

trait EnsuresRecaptchaVerifiedWhenEnabled
{
    public string $gRecaptchaResponse = '';

    /**
     * Whether reCAPTCHA is forced for this submission (requires keys).
     */
    protected function usesRecaptchaForRequests(): bool
    {
        return auth_recaptcha_enforced();
    }

    /**
     * Validate form fields first, then reCAPTCHA when enabled.
     *
     * reCAPTCHA tokens are single-use; deferring verification avoids burning a token on field errors.
     *
     * @param  array<string, array<int, mixed>>  $formRules
     * @return array<string, mixed>
     *
     * @throws ValidationException
     */
    protected function validateFormThenRecaptchaIfEnabled(array $formRules): array
    {
        $validated = $this->validate($formRules);

        if (! $this->usesRecaptchaForRequests()) {
            return $validated;
        }

        try {
            $recaptchaValidated = $this->validate([
                'gRecaptchaResponse' => ['required', 'string', 'captcha'],
            ]);

            return array_merge($validated, $recaptchaValidated);
        } catch (ValidationException $exception) {
            $this->resetRecaptchaAfterFailure();

            throw $exception;
        }
    }

    protected function resetRecaptchaAfterFailure(): void
    {
        $this->clearRecaptchaState();
        $this->dispatch('reset-recaptcha');
    }

    protected function clearRecaptchaState(): void
    {
        $this->reset('gRecaptchaResponse');
    }

    /**
     * Google `data-callback` function name registered on window (must be unique per auth page).
     */
    abstract protected function recaptchaDataCallback(): string;
}
