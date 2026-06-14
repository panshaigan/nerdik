<?php

namespace App\Livewire\Profile\Concerns;

use Illuminate\Validation\ValidationException;

trait ReportsProfileTabValidation
{
    /**
     * @template TReturn
     *
     * @param  callable(): TReturn  $callback
     * @return TReturn
     */
    protected function reportProfileTabValidation(string $tab, callable $callback): mixed
    {
        try {
            $result = $callback();

            $this->dispatch('profile-tab-validation-cleared', tab: $tab);

            return $result;
        } catch (ValidationException $exception) {
            $this->dispatch('profile-tab-validation-failed', tab: $tab);

            throw $exception;
        }
    }
}
