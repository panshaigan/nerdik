<?php

namespace App\Livewire\Activities;

use App\Models\Organization;
use App\Models\User;
use Livewire\Component;

class OrganizationBadgeContact extends Component
{
    public Organization $organization;

    public ?User $user = null;

    public string $size = 'md';

    public string $nameClass = '';

    public ?string $subline = null;

    public bool $avatarOnly = false;

    public bool $trackNavAvatar = false;

    public ?string $contactTooltip = null;

    public string $containerClass = 'inline-flex min-w-0';

    public bool $modalOpen = false;

    public function openModal(): void
    {
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
    }

    public function render()
    {
        return view('livewire.activities.organization-badge-contact');
    }
}
