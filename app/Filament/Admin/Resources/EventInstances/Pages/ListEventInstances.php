<?php

namespace App\Filament\Admin\Resources\EventInstances\Pages;

use App\Filament\Admin\Resources\EventInstances\EventInstanceResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventInstances extends ListRecords
{
    protected static string $resource = EventInstanceResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
