<?php

namespace App\Filament\Admin\Resources\TagAliases\Pages;

use App\Filament\Admin\Resources\TagAliases\TagAliasResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTagAlias extends CreateRecord
{
    protected static string $resource = TagAliasResource::class;
}
