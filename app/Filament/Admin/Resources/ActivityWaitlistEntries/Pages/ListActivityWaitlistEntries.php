<?php

namespace App\Filament\Admin\Resources\ActivityWaitlistEntries\Pages;

use App\Filament\Admin\Resources\ActivityWaitlistEntries\ActivityWaitlistEntryResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityWaitlistEntries extends ListRecords
{
    protected static string $resource = ActivityWaitlistEntryResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
