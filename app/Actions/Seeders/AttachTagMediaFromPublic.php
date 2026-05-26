<?php

declare(strict_types=1);

namespace App\Actions\Seeders;

use App\Models\Tag;

final class AttachTagMediaFromPublic
{
    /**
     * @param  list<string>  $sources  Paths relative to the public directory.
     */
    public function __invoke(Tag $tag, array $sources): void
    {
        app(AttachModelMediaFromPublic::class)($tag, $sources);
    }
}
