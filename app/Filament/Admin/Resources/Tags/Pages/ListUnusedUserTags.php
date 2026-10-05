<?php

namespace App\Filament\Admin\Resources\Tags\Pages;

use App\Filament\Admin\Resources\Tags\TagResource;
use Filament\Resources\Pages\ListRecords;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class ListUnusedUserTags extends ListRecords
{
    protected static string $resource = TagResource::class;

    protected static ?string $title = 'Unused user tags';

    protected static ?string $navigationLabel = 'Unused user tags';

    public function table(Table $table): Table
    {
        return $table->modifyQueryUsing(
            fn (Builder $query): Builder => $query->unused()->createdByNonAdminUser(),
        );
    }

    protected function getHeaderActions(): array
    {
        return [];
    }
}
