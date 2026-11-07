<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Services\GroupMembershipService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Component;
use Mary\Traits\Toast;

class MyGroups extends Component
{
    use Toast;

    public bool $createModalOpen = false;

    public string $name = '';

    public string $description = '';

    public function openCreateModal(): void
    {
        $this->reset(['name', 'description']);
        $this->createModalOpen = true;
    }

    public function closeCreateModal(): void
    {
        $this->createModalOpen = false;
    }

    public function createGroup(GroupMembershipService $membership): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $this->validate([
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        try {
            $group = $membership->create($user, $this->name, $this->description !== '' ? $this->description : null);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->success(__('ui.groups.created'));
        $this->closeCreateModal();
        $this->redirect(route('groups.show', $group), navigate: true);
    }

    public function render()
    {
        $user = Auth::user();

        $groups = $user === null
            ? collect()
            : Group::query()
                ->forUser($user)
                ->orderBy('name')
                ->get();

        return view('livewire.groups.my-groups', [
            'groups' => $groups,
        ]);
    }
}
