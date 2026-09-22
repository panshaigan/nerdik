<?php

declare(strict_types=1);

namespace App\Livewire\EntityLinks;

use App\Models\EntityLink;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Support\Collection;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class ManageEntityLinks extends Component
{
    use Toast;

    public string $linkableType = '';

    public int $linkableId = 0;

    public bool $showList = true;

    public bool $showAddButton = false;

    public string $appearance = 'default';

    public string $dataUi = 'entity-links';

    public string $linkName = '';

    public string $linkUrl = '';

    public ?int $editingLinkId = null;

    public bool $confirmDeleteOpen = false;

    public ?int $pendingDeleteLinkId = null;

    public bool $listenForOpenAdd = true;

    public string $instanceSuffix = 'default';

    public function mount(
        ?Model $linkable = null,
        ?string $linkableType = null,
        ?int $linkableId = null,
        bool $showList = true,
        bool $showAddButton = false,
        bool $listenForOpenAdd = true,
        string $appearance = 'default',
        string $dataUi = 'entity-links',
        string $instanceSuffix = 'default',
    ): void {
        if ($linkable instanceof Model) {
            $this->linkableType = $linkable->getMorphClass();
            $this->linkableId = (int) $linkable->getKey();
        } else {
            $this->linkableType = (string) $linkableType;
            $this->linkableId = (int) $linkableId;
        }

        abort_unless($this->linkableType !== '' && $this->linkableId > 0, 404);

        $this->showList = $showList;
        $this->showAddButton = $showAddButton;
        $this->listenForOpenAdd = $listenForOpenAdd;
        $this->appearance = $appearance;
        $this->dataUi = $dataUi;
        $this->instanceSuffix = $instanceSuffix;
    }

    public function listenerKey(): string
    {
        return $this->linkableType.'-'.$this->linkableId;
    }

    #[On('open-add-entity-link')]
    public function handleOpenAdd(string $key = ''): void
    {
        if (! $this->listenForOpenAdd || $key !== $this->listenerKey()) {
            return;
        }

        $this->openAddLinkModal();
    }

    #[On('entity-links-changed')]
    public function refreshFromSibling(string $key = ''): void
    {
        if ($key !== $this->listenerKey()) {
            return;
        }
    }

    public function openAddLinkModal(): void
    {
        $this->authorizeManage();
        $this->resetLinkForm();
        $this->openModal();
    }

    public function openEditLinkModal(int $linkId): void
    {
        $this->authorizeManage();
        $link = $this->findOwnedLink($linkId);

        $this->editingLinkId = $link->id;
        $this->linkName = $link->name;
        $this->linkUrl = $link->url;
        $this->openModal();
    }

    public function saveLink(): void
    {
        $this->authorizeManage();

        $this->linkUrl = $this->normalizeLinkUrl($this->linkUrl);

        $validated = $this->validate([
            'linkName' => ['required', 'string', 'max:100'],
            'linkUrl' => ['required', 'url:http,https', 'max:2048'],
        ], [], [
            'linkName' => __('ui.entity_links.name'),
            'linkUrl' => __('ui.entity_links.url'),
        ]);

        $linkable = $this->resolveLinkable();

        if ($this->editingLinkId !== null) {
            $link = $this->findOwnedLink($this->editingLinkId);
            $link->update([
                'name' => $validated['linkName'],
                'url' => $validated['linkUrl'],
            ]);
            $this->success(__('ui.entity_links.updated'));
        } else {
            $nextSort = (int) $linkable->links()->max('sort_order') + 1;
            $linkable->links()->create([
                'name' => $validated['linkName'],
                'url' => $validated['linkUrl'],
                'sort_order' => $nextSort,
            ]);
            $this->success(__('ui.entity_links.created'));
        }

        $this->closeModal();
        $this->resetLinkForm();
        $this->dispatch('entity-links-changed', key: $this->listenerKey());
    }

    public function confirmDeleteLink(int $linkId): void
    {
        $this->authorizeManage();
        $this->findOwnedLink($linkId);
        $this->pendingDeleteLinkId = $linkId;
        $this->confirmDeleteOpen = true;
    }

    public function deleteLink(): void
    {
        $this->authorizeManage();

        if ($this->pendingDeleteLinkId === null) {
            return;
        }

        $link = $this->findOwnedLink($this->pendingDeleteLinkId);
        $link->delete();

        $this->confirmDeleteOpen = false;
        $this->pendingDeleteLinkId = null;
        $this->success(__('ui.entity_links.deleted'));
        $this->dispatch('entity-links-changed', key: $this->listenerKey());
    }

    public function closeConfirmDelete(): void
    {
        $this->confirmDeleteOpen = false;
        $this->pendingDeleteLinkId = null;
    }

    public function render()
    {
        $linkable = $this->resolveLinkable();
        $user = auth()->user();
        $canManage = $user instanceof User && $user->canManageEntityLinks($linkable);
        /** @var Collection<int, EntityLink> $links */
        $links = $linkable->links()->get();

        return view('livewire.entity-links.manage-entity-links', [
            'links' => $links,
            'canManage' => $canManage,
            'listenerKey' => $this->listenerKey(),
            'modalId' => $this->modalId(),
        ]);
    }

    private function modalId(): string
    {
        return 'entity-links-modal-'.$this->listenerKey().'-'.$this->instanceSuffix;
    }

    private function normalizeLinkUrl(string $url): string
    {
        $url = trim($url);
        if ($url === '') {
            return $url;
        }

        if (! preg_match('#^[a-z][a-z0-9+.-]*://#i', $url)) {
            return 'https://'.$url;
        }

        return $url;
    }

    private function openModal(): void
    {
        $modalId = json_encode($this->modalId(), JSON_THROW_ON_ERROR);
        $this->js(<<<JS
            queueMicrotask(() => {
                document.getElementById({$modalId})?.showModal();
            });
        JS);
    }

    private function closeModal(): void
    {
        $modalId = json_encode($this->modalId(), JSON_THROW_ON_ERROR);
        $this->js(<<<JS
            queueMicrotask(() => {
                document.getElementById({$modalId})?.close();
            });
        JS);
    }

    private function resetLinkForm(): void
    {
        $this->editingLinkId = null;
        $this->linkName = '';
        $this->linkUrl = '';
        $this->resetValidation();
    }

    private function authorizeManage(): void
    {
        $user = auth()->user();
        abort_unless(
            $user instanceof User && $user->canManageEntityLinks($this->resolveLinkable()),
            403
        );
    }

    private function resolveLinkable(): Model
    {
        $class = Relation::getMorphedModel($this->linkableType) ?? $this->linkableType;
        abort_unless(is_string($class) && is_subclass_of($class, Model::class), 404);

        $linkable = $class::query()->find($this->linkableId);
        abort_unless($linkable instanceof Model, 404);

        return $linkable;
    }

    private function findOwnedLink(int $linkId): EntityLink
    {
        $link = EntityLink::query()
            ->where('linkable_type', $this->linkableType)
            ->where('linkable_id', $this->linkableId)
            ->whereKey($linkId)
            ->first();

        abort_unless($link instanceof EntityLink, 404);

        return $link;
    }
}
