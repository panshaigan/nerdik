<?php

namespace App\Filament\Admin\Resources\ActivityProposals;

use App\Filament\Admin\Resources\ActivityProposals\Pages\CreateActivityProposal;
use App\Filament\Admin\Resources\ActivityProposals\Pages\EditActivityProposal;
use App\Filament\Admin\Resources\ActivityProposals\Pages\ListActivityProposals;
use App\Filament\Admin\Resources\ActivityProposals\Schemas\ActivityProposalForm;
use App\Filament\Admin\Resources\ActivityProposals\Tables\ActivityProposalsTable;
use App\Models\ActivityProposal;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivityProposalResource extends Resource
{
    protected static ?string $model = ActivityProposal::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    public static function form(Schema $schema): Schema
    {
        return ActivityProposalForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivityProposalsTable::configure($table);
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
            'index' => ListActivityProposals::route('/'),
            'create' => CreateActivityProposal::route('/create'),
            'edit' => EditActivityProposal::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }
}
