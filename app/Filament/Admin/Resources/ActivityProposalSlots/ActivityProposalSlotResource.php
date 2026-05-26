<?php

namespace App\Filament\Admin\Resources\ActivityProposalSlots;

use App\Filament\Admin\Resources\ActivityProposalSlots\Pages\CreateActivityProposalSlot;
use App\Filament\Admin\Resources\ActivityProposalSlots\Pages\EditActivityProposalSlot;
use App\Filament\Admin\Resources\ActivityProposalSlots\Pages\ListActivityProposalSlots;
use App\Filament\Admin\Resources\ActivityProposalSlots\Schemas\ActivityProposalSlotForm;
use App\Filament\Admin\Resources\ActivityProposalSlots\Tables\ActivityProposalSlotsTable;
use App\Models\ActivityProposalSlot;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;

class ActivityProposalSlotResource extends Resource
{
    protected static ?string $model = ActivityProposalSlot::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ActivityProposalSlotForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityProposalSlotsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListActivityProposalSlots::route('/'),
            'create' => CreateActivityProposalSlot::route('/create'),
            'edit' => EditActivityProposalSlot::route('/{record}/edit'),
        ];
    }
}
