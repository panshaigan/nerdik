<?php

declare(strict_types=1);

namespace App\Livewire\Layout;

use App\Models\Organization;
use App\Models\User;
use App\Support\Search\GlobalNavSearch as GlobalNavSearchService;
use Illuminate\Contracts\View\View;
use Livewire\Component;

class GlobalNavSearch extends Component
{
    public string $q = '';

    public bool $suggestionsDismissed = false;

    public bool $organizationPreviewModalOpen = false;

    public ?int $previewOrganizationId = null;

    public bool $userPreviewModalOpen = false;

    public ?int $previewUserId = null;

    /**
     * @return array{
     *     events: list<array<string, mixed>>,
     *     activities: list<array<string, mixed>>,
     *     organizations: list<array<string, mixed>>,
     *     users: list<array<string, mixed>>,
     *     places: list<array<string, mixed>>,
     *     tags: list<array<string, mixed>>
     * }
     */
    public function suggestions(): array
    {
        return GlobalNavSearchService::search($this->q, auth()->user());
    }

    public function submitSearch(): void
    {
        $term = trim($this->q);
        if ($term === '') {
            return;
        }

        $this->redirect(route('search.index', ['q' => $term]), navigate: true);
    }

    public function dismissSuggestions(): void
    {
        $this->suggestionsDismissed = true;
    }

    public function reopenSuggestionsIfEligible(): void
    {
        if (mb_strlen(trim($this->q)) >= 2) {
            $this->suggestionsDismissed = false;
        }
    }

    public function updatedQ(): void
    {
        $this->suggestionsDismissed = false;
    }

    public function openOrganizationPreview(int $organizationId): void
    {
        Organization::query()->whereKey($organizationId)->firstOrFail();

        $this->previewOrganizationId = $organizationId;
        $this->organizationPreviewModalOpen = true;
        $this->dispatch('global-nav-search-close-mobile');
    }

    public function openUserPreview(int $userId): void
    {
        User::query()
            ->whereKey($userId)
            ->where('is_deleted', false)
            ->firstOrFail();

        $this->previewUserId = $userId;
        $this->userPreviewModalOpen = true;
        $this->dispatch('global-nav-search-close-mobile');
    }

    public function updatedOrganizationPreviewModalOpen(bool $value): void
    {
        if (! $value) {
            $this->previewOrganizationId = null;
        }
    }

    public function updatedUserPreviewModalOpen(bool $value): void
    {
        if (! $value) {
            $this->previewUserId = null;
        }
    }

    public function render(): View
    {
        $suggestions = $this->suggestions();

        return view('livewire.layout.global-nav-search', [
            'suggestions' => $suggestions,
            'hasSuggestions' => self::hasAnySuggestions($suggestions),
            'showSuggestions' => $this->shouldShowSuggestions($suggestions),
        ]);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $suggestions
     */
    private function shouldShowSuggestions(array $suggestions): bool
    {
        if ($this->suggestionsDismissed) {
            return false;
        }

        if (mb_strlen(trim($this->q)) < 2) {
            return false;
        }

        return self::hasAnySuggestions($suggestions);
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $suggestions
     * @return list<array<string, mixed>>
     */
    public static function flattenSuggestions(array $suggestions): array
    {
        $flat = [];
        $index = 0;

        foreach (['events', 'activities', 'organizations', 'users', 'places', 'tags'] as $group) {
            foreach ($suggestions[$group] ?? [] as $item) {
                $flat[] = [
                    ...$item,
                    'flat_index' => $index,
                ];
                $index++;
            }
        }

        return $flat;
    }

    /**
     * @param  array<string, list<array<string, mixed>>>  $suggestions
     */
    public static function hasAnySuggestions(array $suggestions): bool
    {
        foreach ($suggestions as $group) {
            if ($group !== []) {
                return true;
            }
        }

        return false;
    }
}
