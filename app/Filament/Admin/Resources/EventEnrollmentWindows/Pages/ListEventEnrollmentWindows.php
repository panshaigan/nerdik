<?php

namespace App\Filament\Admin\Resources\EventEnrollmentWindows\Pages;

use App\Filament\Admin\Resources\EventEnrollmentWindows\EventEnrollmentWindowResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListEventEnrollmentWindows extends ListRecords
{
    protected static string $resource = EventEnrollmentWindowResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
