<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\Tag;
use Illuminate\Support\Collection;

final class SampleActivityTagPools
{
    /**
     * @param  Collection<int, Tag>  $gameTags
     * @param  Collection<int, Tag>  $genreTags
     * @param  Collection<int, Tag>  $mechanicTags
     * @param  Collection<int, Tag>  $formatTags
     * @param  Collection<int, Tag>  $otherTags
     * @param  Collection<int, Tag>  $triggerTags
     */
    public function __construct(
        public Collection $gameTags,
        public Collection $genreTags,
        public Collection $mechanicTags,
        public Collection $formatTags,
        public Collection $otherTags,
        public Collection $triggerTags,
    ) {}
}
