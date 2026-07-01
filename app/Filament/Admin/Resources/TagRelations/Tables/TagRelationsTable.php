<?php

namespace App\Filament\Admin\Resources\TagRelations\Tables;

use App\Filament\Tables\Columns\BelongsToColumn;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Table;

class TagRelationsTable
{
    public static function configure(Table $table): Table
    {
        return BelongsToColumn::withEagerLoads($table, [
            'tag.translations',
            'relatedTag.translations',
        ])
            ->columns([
                BelongsToColumn::record('tag', searchable: true),
                BelongsToColumn::record('relatedTag', label: 'Related tag', searchable: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
