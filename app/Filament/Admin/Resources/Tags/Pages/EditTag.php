<?php

namespace App\Filament\Admin\Resources\Tags\Pages;

use App\Filament\Admin\Resources\Tags\TagResource;
use App\Services\ActivitySuggestedBringService;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\Support\Htmlable;

class EditTag extends EditRecord
{
    protected static string $resource = TagResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }

    public function getTitle(): string|Htmlable
    {
        $tag = $this->getRecord();
        $tag->loadMissing('translations');

        $en = $tag->displayLabel('en');
        $pl = $tag->displayLabel('pl');
        $fallback = '#'.$tag->getKey();

        $labels = collect([$en, $pl])
            ->reject(fn (string $label): bool => $label === $fallback)
            ->unique()
            ->values();

        $label = $labels->isNotEmpty() ? $labels->implode(' / ') : $fallback;

        return __('filament-panels::resources/pages/edit-record.title', [
            'label' => $label,
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        $tag = $this->getRecord();
        $tag->loadMissing('suggestedBringItems');
        $data['suggested_bring_labels'] = $tag->suggestedBringItems
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
