<?php

declare(strict_types=1);

namespace App\Livewire\PageAliases;

use App\Services\PageAliasService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class PageAliasModal extends Component
{
    use Toast;

    public bool $modalOpen = false;

    public string $targetPath = '/';

    public string $alias = '';

    #[On('open-page-alias-modal')]
    public function handleOpen(string $path): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $normalizedPath = trim($path);
        if ($normalizedPath === '' || ! str_starts_with($normalizedPath, '/')) {
            $normalizedPath = '/'.ltrim($normalizedPath, '/');
        }

        $this->targetPath = $normalizedPath;
        $this->alias = '';
        $this->modalOpen = true;
    }

    public function closeModal(): void
    {
        $this->modalOpen = false;
        $this->alias = '';
    }

    public function submit(PageAliasService $pageAliases): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        try {
            if ($user->is_admin) {
                $pageAliases->create($user, $this->alias, $this->targetPath);
                $this->success(__('ui.page_aliases.created'));
            } else {
                $pageAliases->request($user, $this->alias, $this->targetPath);
                $this->success(__('ui.user_requests.sent'));
                $this->dispatch('user-requests-updated');
            }
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->closeModal();
    }

    public function modalTitle(): string
    {
        $user = Auth::user();

        return $user?->is_admin === true
            ? __('ui.page_aliases.create')
            : __('ui.page_aliases.request');
    }

    public function submitLabel(): string
    {
        $user = Auth::user();

        return $user?->is_admin === true
            ? __('ui.page_aliases.create_action')
            : __('ui.user_requests.send_request');
    }

    public function previewUrl(): string
    {
        $alias = strtolower(trim($this->alias));
        if ($alias === '') {
            return url('/');
        }

        return url('/'.$alias);
    }

    public function render()
    {
        return view('livewire.page-aliases.page-alias-modal');
    }
}
