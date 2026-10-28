<?php

namespace App\Filament\Admin\Resources\Tags\Pages;

use App\Filament\Admin\Resources\Tags\TagResource;
use App\Services\ActivitySuggestedBringService;
use Filament\Resources\Pages\CreateRecord;

class CreateTag extends CreateRecord
{
    protected static string $resource = TagResource::class;

    protected function afterCreate(): void
    {
        $state = $this->form->getState();
        $labels = collect($state['suggested_bring_labels'] ?? [])
            ->filter(fn ($row): bool => is_array($row) && (
                trim((string) ($row['label_en'] ?? '')) !== ''
                || trim((string) ($row['label_pl'] ?? '')) !== ''
            ))
            ->values()
            ->all();

        app(ActivitySuggestedBringService::class)->syncBringLabelsForBringable($this->getRecord(), $labels);
    }
}
