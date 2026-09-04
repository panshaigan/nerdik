<?php

namespace App\Livewire\Profile;

use Livewire\Attributes\On;
use Livewire\Component;

class ProfileTabs extends Component
{
    /** @var list<string> */
    private const FORM_TAB_ORDER = ['identity', 'contact', 'avatar', 'images', 'notifications', 'advanced'];

    public string $tab = 'identity';

    /** @var array<string, true> */
    public array $tabsWithErrors = [];

    protected array $queryString = [
        'tab' => ['except' => 'identity'],
    ];

    public function mount(): void
    {
        $this->tab = $this->normalizeTab($this->tab);
    }

    public function updatedTab(string $value): void
    {
        $this->tab = $this->normalizeTab($value);
    }

    #[On('profile-tab-validation-failed')]
    public function markTabValidationFailed(string $tab): void
    {
        if (! in_array($tab, self::FORM_TAB_ORDER, true)) {
            return;
        }

        $this->tabsWithErrors[$tab] = true;
        $this->tab = $tab;
    }

    #[On('profile-tab-validation-cleared')]
    public function clearTabValidationErrors(string $tab): void
    {
        unset($this->tabsWithErrors[$tab]);
    }

    public function tabLabel(string $tab, string $label): string
    {
        $escapedLabel = e($label);

        if (! isset($this->tabsWithErrors[$tab])) {
            return $escapedLabel;
        }

        return $escapedLabel.' <span class="badge badge-error badge-xs ms-1" aria-hidden="true">!</span>';
    }

    public function render()
    {
        return view('livewire.profile.profile-tabs');
    }

    private function normalizeTab(?string $tab): string
    {
        return in_array($tab, self::FORM_TAB_ORDER, true)
            ? $tab
            : 'identity';
    }
}
