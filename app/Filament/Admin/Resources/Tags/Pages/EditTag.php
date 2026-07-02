<?php

namespace App\Filament\Admin\Resources\Tags\Pages;

use App\Filament\Admin\Resources\Tags\TagResource;
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
}
