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

        $fixture = base_path('tests/fixtures/tag-sample.jpg');
        $attach = app(AttachModelMediaFromPublic::class);

        $attach->attachFile($rpg, $fixture, 'tests/fixtures/tag-sample-activity-default.jpg');
        $attach->attachFile(
            $rpg,
            $fixture,
            'tests/fixtures/tag-sample-event-default.jpg',
            collection: EventListingImageResolver::EVENT_LISTING_COLLECTION,
        );
    }
}
