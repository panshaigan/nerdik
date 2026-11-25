<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\PageAliases\Pages;

use App\Filament\Admin\Resources\PageAliases\PageAliasResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageAliases extends ListRecords
{
    protected static string $resource = PageAliasResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}
