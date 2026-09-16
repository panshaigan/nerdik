<?php

namespace App\Filament\Admin\Resources\EventSeries\Pages;

use App\Filament\Admin\Resources\EventSeries\EventSeriesResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditEventSeries extends EditRecord
{
    protected static string $resource = EventSeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
