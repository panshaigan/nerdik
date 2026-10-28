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
            ->map(fn ($item): array => ['label' => (string) $item->label])
            ->values()
            ->all();

        return $data;
    }

    protected function afterSave(): void
    {
        $state = $this->form->getState();
        $labels = collect($state['suggested_bring_labels'] ?? [])
            ->map(fn ($row) => is_array($row) ? trim((string) ($row['label'] ?? '')) : '')
            ->filter(fn (string $label): bool => $label !== '')
            ->values()
            ->all();

        app(ActivitySuggestedBringService::class)->syncBringLabelsForBringable($this->getRecord(), $labels);
    }
}
