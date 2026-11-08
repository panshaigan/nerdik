<?php

declare(strict_types=1);

namespace App\Livewire\Groups;

use App\Models\Group;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Services\GroupDiscoveryPreferencesService;
use App\Services\TagSelectionService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Mary\Traits\Toast;

class GroupTagsForm extends Component
{
    use Toast;

    #[Locked]
    public int $groupId;

    /** @var list<int> */
    public array $tag_ids = [];

    /** @var list<array{label?: string, category_id?: int}> */
    public array $new_tags = [];

    public function mount(int $groupId): void
    {
        $this->groupId = $groupId;
        $this->hydrateFromGroup($this->resolveGroup());
    }

    /**
     * @return array<string, mixed>
     */
    public function getTagPickerConfigProperty(): array
    {
        $locale = app()->getLocale();
        $tagSelection = app(TagSelectionService::class);

        return [
            'locale' => $locale,
            'semanticByTagCategory' => config('activity-badges.semantic_by_tag_category', []),
            'defaultTaxonomySemantic' => config('activity-badges.semantic_by_kind.taxonomy_tag', 'neutral'),
            'categories' => TagCategory::query()
                ->with('translations')
                ->orderBy('key')
                ->get()
                ->map(static fn (TagCategory $cat) => [
                    'id' => (int) $cat->id,
                    'key' => (string) $cat->key,
                    'name' => (string) $cat->name($locale),
                ])
                ->values()
                ->all(),
            'tags' => $tagSelection->tagsPayloadForActivityPicker(
                Tag::query()->forActivityFormPicker()->get(),
                $locale,
            ),
            'initialSelectedIds' => $this->tag_ids,
            'initialNewTags' => $this->new_tags,
            'ignoreActivityTypeContexts' => true,
            'strings' => [
                'createTag' => __('ui.tags.create_tag'),
                'auto' => __('ui.tags.auto'),
            ],
        ];
    }

    public function updatedTagIds(): void
    {
        $this->skipRender();
    }

    public function updatedNewTags(): void
    {
        $this->skipRender();
    }

    public function save(GroupDiscoveryPreferencesService $preferences): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $group = $this->resolveGroup();

        $this->validate([
            'tag_ids' => ['nullable', 'array'],
            'tag_ids.*' => ['integer', 'exists:tags,id'],
            'new_tags' => ['nullable', 'array'],
            'new_tags.*.label' => ['nullable', 'string', 'max:255'],
            'new_tags.*.category_id' => ['nullable', 'integer', 'exists:tag_categories,id'],
        ]);

        try {
            $preferences->syncTags($group, $user, $this->tag_ids, $this->new_tags);
        } catch (ValidationException $exception) {
            $this->error((string) collect($exception->errors())->flatten()->first());

            return;
        }

        $this->new_tags = [];
        $this->hydrateFromGroup($group->refresh()->load('tags'));
        $this->success(__('ui.groups.tags_saved'));
        $this->dispatch('group-tags-updated');
    }

    public function render()
    {
        $group = $this->resolveGroup()->loadMissing('tags.translations');
        $locale = app()->getLocale();
        $canManage = Auth::user()?->canModifyEntity($group) ?? false;

        $tagLabels = $group->tags
            ->map(static fn (Tag $tag) => $tag->displayLabel($locale))
            ->filter()
            ->values()
            ->all();

        return view('livewire.groups.group-tags-form', [
            'group' => $group,
            'canManage' => $canManage,
            'tagLabels' => $tagLabels,
        ]);
    }

    private function hydrateFromGroup(Group $group): void
    {
        $group->loadMissing('tags');

        $this->tag_ids = $group->tags
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->new_tags = [];
    }

    private function resolveGroup(): Group
    {
        return Group::query()
            ->visibleTo(Auth::user())
            ->whereKey($this->groupId)
            ->firstOrFail();
    }
}
