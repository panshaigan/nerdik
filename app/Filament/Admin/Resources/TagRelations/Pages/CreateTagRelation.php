<?php

namespace App\Filament\Admin\Resources\TagRelations\Pages;

use App\Filament\Admin\Resources\TagRelations\TagRelationResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTagRelation extends CreateRecord
{
    protected static string $resource = TagRelationResource::class;
}
