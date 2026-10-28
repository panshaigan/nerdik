<?php

namespace App\Filament\Admin\Resources\ActivityTypes\Pages;

use App\Filament\Admin\Resources\ActivityTypes\ActivityTypeResource;
use App\Services\ActivitySuggestedBringService;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditActivityType extends EditRecord
{
    protected static string $resource = ActivityTypeResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $type = $this->getRecord();
        $type->loadMissing('suggestedBringItems');
        $data['suggested_bring_labels'] = $type->suggestedBringItems
            ->map(function ($item): array {
                $item->loadMissing('translations');

                return [
                    'label_en' => (string) ($item->translations->firstWhere('locale', 'en')?->label ?? ''),
                    'label_pl' => (string) ($item->translations->firstWhere('locale', 'pl')?->label ?? ''),
                ];
            })
            ->values()
            ->all();

        return $data;
    }

    protected function afterSave(): void
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
