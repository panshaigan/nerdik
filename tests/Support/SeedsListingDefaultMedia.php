<?php

declare(strict_types=1);

namespace Tests\Support;

use App\Actions\Seeders\AttachModelMediaFromPublic;
use App\Models\ActivityType;
use App\Support\Ui\EventListingImageResolver;
use Database\Seeders\ActivityTypeSeeder;

trait SeedsListingDefaultMedia
{
    protected function seedListingDefaultMedia(): void
    {
        $this->seed(ActivityTypeSeeder::class);

        $rpg = ActivityType::findBySlug(ActivityType::SLUG_RPG);
        if ($rpg === null) {
            return;
        }

        $attach = app(AttachModelMediaFromPublic::class);

        $attach($rpg, ['images/listing/activity-type-rpg.jpg']);
        $attach(
            $rpg,
            ['images/listing/event-default.jpg'],
            ['listing_role' => EventListingImageResolver::LISTING_ROLE],
        );
    }
}
