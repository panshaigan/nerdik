<?php

namespace App\Filament\Admin\Resources\ActivityUsers\Pages;

use App\Filament\Admin\Resources\ActivityUsers\ActivityUserResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListActivityUsers extends ListRecords
{
    protected static string $resource = ActivityUserResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
