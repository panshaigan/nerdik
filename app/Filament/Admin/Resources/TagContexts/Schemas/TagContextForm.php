<?php

namespace App\Filament\Admin\Resources\TagContexts\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use App\Models\TagContext;
use Filament\Forms\Components\Select;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;

class TagContextForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::tag('tag_id')
                    ->required(),
                Select::make('context_type')
                    ->options([
                        TagContext::CONTEXT_TYPE_ACTIVITY_TYPE => 'Activity type',
                    ])
                    ->default(TagContext::CONTEXT_TYPE_ACTIVITY_TYPE)
                    ->required()
                    ->live(),
                BelongsToSelect::activityType('context_id', relationship: null)
                    ->visible(fn (Get $get): bool => $get('context_type') === TagContext::CONTEXT_TYPE_ACTIVITY_TYPE)
                    ->required(fn (Get $get): bool => $get('context_type') === TagContext::CONTEXT_TYPE_ACTIVITY_TYPE),
            ]);
    }
}
