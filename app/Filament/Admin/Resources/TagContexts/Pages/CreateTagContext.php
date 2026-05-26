<?php

namespace App\Filament\Admin\Resources\TagContexts\Pages;

use App\Filament\Admin\Resources\TagContexts\TagContextResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTagContext extends CreateRecord
{
    protected static string $resource = TagContextResource::class;
}
