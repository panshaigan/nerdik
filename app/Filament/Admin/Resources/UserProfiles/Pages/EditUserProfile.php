<?php

namespace App\Filament\Admin\Resources\UserProfiles\Pages;

use App\Filament\Admin\Resources\UserProfiles\UserProfileResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditUserProfile extends EditRecord
{
    protected static string $resource = UserProfileResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}
