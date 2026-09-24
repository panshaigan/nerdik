<?php

namespace App\Filament\Admin\Resources\ActivitySeries\Pages;

use App\Filament\Admin\Resources\ActivitySeries\ActivitySeriesResource;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;

class EditActivitySeries extends EditRecord
{
    protected static string $resource = ActivitySeriesResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
            ForceDeleteAction::make(),
            RestoreAction::make(),
        ];
    }
}
