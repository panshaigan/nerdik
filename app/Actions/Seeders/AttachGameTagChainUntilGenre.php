<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\Activity;
use App\Models\Tag;
use App\Models\TagCategory;
use Illuminate\Support\Collection;

final class AttachGameTagChainUntilGenre
{
    public function __invoke(Activity $activity, Tag $gameTag): void
    {
        /** @var Collection<int, Tag> $queue */
        $queue = collect([$gameTag]);
        $visited = [];

        while ($queue->isNotEmpty()) {
            $tag = $queue->shift();

            if (isset($visited[$tag->id])) {
                continue;
            }

            $visited[$tag->id] = true;
            $activity->tags()->syncWithoutDetaching([$tag->id]);

            $tag->loadMissing(['tagCategory', 'relatedTags.tagCategory']);

            if ($tag->category === TagCategory::KEY_GENRE) {
                return;
            }

            foreach ($tag->relatedTags as $relatedTag) {
                if (! isset($visited[$relatedTag->id])) {
                    $queue->push($relatedTag);
                }
            }
        }
    }
}
