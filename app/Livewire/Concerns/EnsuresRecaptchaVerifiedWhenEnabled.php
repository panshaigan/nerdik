<?php

namespace App\Livewire\Concerns;

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
     * Merge reCAPTCHA rules when enabled and configured.
     *
     * @param  array<string, array<int, mixed>>  $rules
     * @return array<string, array<int, mixed>>
     */
    protected function rulesIncludingRecaptchaIfEnabled(array $rules): array
    {
        if (! $this->usesRecaptchaForRequests()) {
            return $rules;
        }

        return array_merge($rules, [
            'gRecaptchaResponse' => ['required', 'string', 'captcha'],
        ]);
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
