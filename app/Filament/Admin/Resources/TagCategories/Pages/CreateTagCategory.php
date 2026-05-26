<?php

namespace App\Filament\Admin\Resources\TagCategories\Pages;

use App\Filament\Admin\Resources\TagCategories\TagCategoryResource;
use Filament\Resources\Pages\CreateRecord;

class CreateTagCategory extends CreateRecord
{
    protected static string $resource = TagCategoryResource::class;
}
