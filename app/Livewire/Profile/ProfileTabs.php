<?php

namespace App\Livewire\Profile;

use Livewire\Component;

class ProfileTabs extends Component
{
    public string $tab = 'identity';

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

    public function render()
    {
        return view('livewire.profile.profile-tabs');
    }

    private function normalizeTab(?string $tab): string
    {
        return in_array($tab, ['identity', 'contact', 'avatar', 'notifications', 'advanced'], true)
            ? $tab
            : 'identity';
    }
}
