<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Tag;
use App\Services\GroupPublicDirectoryService;
use App\Support\Browse\BrowseTagSelectorPayload;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Renderless;
use Livewire\Component;
use Livewire\WithPagination;

class GroupFindDirectory extends Component
{
    use WithPagination;

    public string $nameQuery = '';

    /** @var list<int> */
    public array $tag_ids = [];

    /**
     * @var list<array{label: string, category_id: int|string}>
     */
    public array $new_tags = [];

    public bool $nearMyPlayArea = false;

    public function updatedNameQuery(): void
    {
        $this->resetPage();
    }

    public function updatedTagIds(): void
    {
        $this->resetPage();
    }

    public function updatedNearMyPlayArea(): void
    {
        $this->resetPage();
    }

    /**
     * @param  list<int|string>  $tag_ids
     * @param  list<array{label: string, category_id: int|string}>  $new_tags
     */
    public function syncBrowseTagsFromSelector(array $tag_ids, array $new_tags = []): void
    {
        $this->tag_ids = array_values(array_unique(array_filter(
            array_map(static fn ($id) => (int) $id, $tag_ids),
            static fn (int $id) => $id > 0
        )));

        $this->new_tags = collect($new_tags)
            ->filter(static fn ($row) => is_array($row) && isset($row['label'], $row['category_id']))
            ->map(static fn (array $row) => [
                'label' => trim((string) $row['label']),
                'category_id' => (int) $row['category_id'],
            ])
            ->values()
            ->all();

        $this->resetPage();
    }

    /**
     * @return array{
     *     tags: list<array<string, mixed>>,
     *     events: list<array<string, mixed>>,
     *     activities: list<array<string, mixed>>
     * }
     */
    #[Renderless]
    public function searchBrowseSuggestions(string $q): array
    {
        $trimmed = trim($q);
        if ($trimmed === '') {
            return [
                'tags' => [],
                'events' => [],
                'activities' => [],
            ];
        }

        $locale = app()->getLocale();
        $categoryContext = BrowseTagSelectorPayload::categoryMapsFromConfig(
            BrowseTagSelectorPayload::categoriesForLocale($locale),
        );
        $tagLimit = (int) config('browse.tag_suggestions.search_limit', 30);

        $tags = Tag::query()->searchForPublicGroupSelector($trimmed, $tagLimit);

        return [
            'tags' => BrowseTagSelectorPayload::fromCollection(
                $tags,
                $locale,
                $categoryContext['namesById'],
                $categoryContext['keysById'],
                includeRelatedIds: false,
            ),
            'events' => [],
            'activities' => [],
        ];
    }

    public function render(GroupPublicDirectoryService $directory): View
    {
        $user = Auth::user();
        $user?->loadMissing('playArea');
        $viewerHasPlayArea = $user?->playArea !== null;

        $groups = $user === null
            ? collect()
            : $directory->discover(
                $user,
                $this->nameQuery,
                $this->tag_ids,
                $this->nearMyPlayArea,
                perPage: 15,
            );

        $locale = app()->getLocale();

        return view('livewire.groups.group-find-directory', [
            'groups' => $groups,
            'viewerHasPlayArea' => $viewerHasPlayArea,
            'locale' => $locale,
            'tags' => Tag::query()->forPublicGroupSelector($this->tag_ids),
        ]);
    }
}
