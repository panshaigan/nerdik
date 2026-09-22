<?php

declare(strict_types=1);

namespace App\Traits;

use App\Models\EntityLink;
use Illuminate\Database\Eloquent\Relations\MorphMany;

trait HasEntityLinks
{
    public function links(): MorphMany
    {
        return $this->morphMany(EntityLink::class, 'linkable')->orderBy('sort_order')->orderBy('id');
    }
}
