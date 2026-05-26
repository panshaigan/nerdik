<?php

namespace App\Filament\Admin\Resources\UserProfiles\Pages;

use App\Filament\Admin\Resources\UserProfiles\UserProfileResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListUserProfiles extends ListRecords
{
    protected static string $resource = UserProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
