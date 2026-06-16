<?php

namespace App\Livewire\Profile\Concerns;

trait WithAdvancedPasswordConfirmation
{
    public bool $confirmingPassword = false;

    public string $passwordConfirmationPassword = '';

    public ?string $passwordConfirmationAction = null;

    protected function openPasswordConfirmation(string $action): void
    {
        $this->passwordConfirmationAction = $action;
        $this->passwordConfirmationPassword = '';
        $this->confirmingPassword = true;
    }

    public function cancelPasswordConfirmation(): void
    {
        $this->confirmingPassword = false;
        $this->passwordConfirmationPassword = '';
        $this->passwordConfirmationAction = null;
    }

    public function runPasswordConfirmation(): void
    {
        $this->validate([
            'passwordConfirmationPassword' => ['required', 'string'],
        ]);

        $action = $this->passwordConfirmationAction;

        if (! is_string($action) || ! method_exists($this, $action)) {
            $this->cancelPasswordConfirmation();

            return;
        }

        $this->{$action}($this->passwordConfirmationPassword);

        $this->cancelPasswordConfirmation();
    }
}
