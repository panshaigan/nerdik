<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\Activity;
use App\Models\TagCategory;

final class AttachSampleActivityTags
{
    public function __construct(
        private AttachGameTagChainUntilGenre $attachGameTagChainUntilGenre,
    ) {}

    public function __invoke(Activity $activity, SampleActivityTagPools $pools): void
    {
        $gameTag = $pools->gameTags->random();

        ($this->attachGameTagChainUntilGenre)($activity, $gameTag);

        $activity->tags()->syncWithoutDetaching($pools->formatTags->random(1)->pluck('id')->all());
        $activity->tags()->syncWithoutDetaching($pools->otherTags->random(1)->pluck('id')->all());
        $activity->tags()->syncWithoutDetaching(
            $pools->triggerTags->random(fake()->numberBetween(1, 3))->pluck('id')->all(),
        );

        if (! $this->activityHasTagCategory($activity, TagCategory::KEY_GAME)) {
            $activity->tags()->syncWithoutDetaching([$gameTag->id]);
        }

        if (! $this->activityHasTagCategory($activity, TagCategory::KEY_GENRE)) {
            $activity->tags()->syncWithoutDetaching([$pools->genreTags->random()->id]);
        }

        if (! $this->activityHasTagCategory($activity, TagCategory::KEY_MECHANIC)) {
            $activity->tags()->syncWithoutDetaching([$pools->mechanicTags->random()->id]);
        }
    }

    private function activityHasTagCategory(Activity $activity, string $categoryKey): bool
    {
        return $activity->tags()
            ->whereHas('tagCategory', fn ($query) => $query->where('key', $categoryKey))
            ->exists();
    }
}
