<?php

use App\Livewire\Profile\Concerns\ReportsProfileTabValidation;
use App\Models\Tag;
use App\Models\TagCategory;
use App\Services\TagSelectionService;
use App\Services\UserPlayPreferencesService;
use Illuminate\Support\Facades\Auth;
use Livewire\Volt\Component;

new class extends Component
{
    use ReportsProfileTabValidation;

    public ?float $playLatitude = null;

    public ?float $playLongitude = null;

    public int $playRadiusKm = UserPlayPreferencesService::DEFAULT_RADIUS_KM;

    public ?string $playLabel = null;

    /** @var list<int> */
    public array $tag_ids = [];

    /** @var list<array{label?: string, category_id?: int}> */
    public array $new_tags = [];

    public function mount(): void
    {
        $user = Auth::user();
        if ($user === null) {
            return;
        }

        $this->hydratePlayPreferences($user);
    }

    private function hydratePlayPreferences(\App\Models\User $user): void
    {
        $user->loadMissing(['playArea', 'interestTags']);

        $playArea = $user->playArea;
        if ($playArea !== null) {
            $this->playLatitude = (float) $playArea->latitude;
            $this->playLongitude = (float) $playArea->longitude;
            $this->playRadiusKm = (int) $playArea->radius_km;
            $this->playLabel = $playArea->label;
        } else {
            $this->playLatitude = null;
            $this->playLongitude = null;
            $this->playRadiusKm = UserPlayPreferencesService::DEFAULT_RADIUS_KM;
            $this->playLabel = null;
        }

        $this->tag_ids = $user->interestTags
            ->pluck('id')
            ->map(fn ($id) => (int) $id)
            ->values()
            ->all();
        $this->new_tags = [];
    }

    /**
     * @return array<string, mixed>
     */
    public function getPlayAreaMapConfigProperty(): array
    {
        return [
            'searchUrl' => route('geocode.search'),
            'reverseUrl' => route('geocode.reverse'),
            'latitude' => $this->playLatitude,
            'longitude' => $this->playLongitude,
            'radiusKm' => $this->playRadiusKm,
            'label' => $this->playLabel,
            'minRadiusKm' => UserPlayPreferencesService::MIN_RADIUS_KM,
            'maxRadiusKm' => UserPlayPreferencesService::MAX_RADIUS_KM,
            'defaultRadiusKm' => UserPlayPreferencesService::DEFAULT_RADIUS_KM,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function getInterestTagPickerConfigProperty(): array
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

    public function setPlayAreaDraft(
        ?float $latitude,
        ?float $longitude,
        int $radiusKm,
        ?string $label,
    ): void {
        $this->playLatitude = $latitude;
        $this->playLongitude = $longitude;
        $this->playRadiusKm = max(
            UserPlayPreferencesService::MIN_RADIUS_KM,
            min(UserPlayPreferencesService::MAX_RADIUS_KM, $radiusKm),
        );
        $normalized = $label !== null ? trim($label) : null;
        $this->playLabel = $normalized === '' ? null : $normalized;
        $this->skipRender();
    }

    public function updatedTagIds(): void
    {
        $this->skipRender();
    }

    public function updatedNewTags(): void
    {
        $this->skipRender();
    }

    public function save(UserPlayPreferencesService $preferences): void
    {
        $this->reportProfileTabValidation('play-preferences', function () use ($preferences): void {
            $user = Auth::user();
            if ($user === null) {
                return;
            }

            $this->validate([
                'playLatitude' => ['nullable', 'numeric', 'between:-90,90'],
                'playLongitude' => ['nullable', 'numeric', 'between:-180,180'],
                'playRadiusKm' => [
                    'integer',
                    'min:'.UserPlayPreferencesService::MIN_RADIUS_KM,
                    'max:'.UserPlayPreferencesService::MAX_RADIUS_KM,
                ],
                'playLabel' => ['nullable', 'string', 'max:255'],
                'tag_ids' => ['nullable', 'array'],
                'tag_ids.*' => ['integer', 'exists:tags,id'],
                'new_tags' => ['nullable', 'array'],
                'new_tags.*.label' => ['nullable', 'string', 'max:255'],
                'new_tags.*.category_id' => ['nullable', 'integer', 'exists:tag_categories,id'],
            ]);

            if ($this->playLatitude !== null && $this->playLongitude !== null) {
                $preferences->savePlayArea(
                    $user,
                    (float) $this->playLatitude,
                    (float) $this->playLongitude,
                    (int) $this->playRadiusKm,
                    $this->playLabel,
                );
            } else {
                $preferences->clearPlayArea($user);
            }

            $preferences->syncInterestTags($user, $this->tag_ids, $this->new_tags);

            $this->new_tags = [];
            $this->dispatch('profile-play-preferences-updated');
        });
    }
}; ?>

<section id="ui-profile-play-preferences-section" class="ui-profile-section" data-ui="profile-play-preferences-section">
    <header class="mb-6">
        <h2 class="text-lg font-medium text-base-content">
            {{ __('ui.profile.play_preferences.title') }}
        </h2>
    </header>

    <x-ui.form-errors :title="__('ui.status.oops')" :description="__('ui.status.form_errors')" icon="o-face-frown" class="!mx-0 mb-4" />

    <div class="space-y-4 overflow-visible" data-ui="profile-play-preferences-play-area">
        <div>
            <h3 class="text-sm font-semibold text-base-content">
                {{ __('ui.profile.availability.play_area_title') }}
            </h3>
        </div>
        @include('livewire.profile.partials.play-area-map', [
            'playAreaMapConfig' => $this->playAreaMapConfig,
        ])
    </div>

    <div class="divider my-8"></div>

    <div class="space-y-3 overflow-visible" data-ui="profile-play-preferences-interest-tags">
        <div>
            <h3 class="text-sm font-semibold text-base-content">
                {{ __('ui.profile.availability.interest_tags_title') }}
            </h3>
        </div>
        <x-activity.category-tags-picker :config="$this->interestTagPickerConfig" />
        <x-field-error :messages="$errors->get('tag_ids')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags.*.label')" class="mt-2" />
        <x-field-error :messages="$errors->get('new_tags.*.category_id')" class="mt-2" />
    </div>

    <div class="mt-8 flex items-center justify-end gap-3" data-ui="profile-play-preferences-save">
        <x-action-message class="me-3" on="profile-play-preferences-updated">{{ __('ui.common.saved') }}</x-action-message>
        <x-button
            type="button"
            class="btn-primary"
            wire:click="save"
            spinner="save"
        >
            {{ __('ui.common.save') }}
        </x-button>
    </div>
</section>
