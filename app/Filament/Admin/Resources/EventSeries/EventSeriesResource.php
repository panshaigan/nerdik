<?php

namespace App\Filament\Admin\Resources\EventSeries;

use App\Filament\Admin\Resources\EventSeries\Pages\CreateEventSeries;
use App\Filament\Admin\Resources\EventSeries\Pages\EditEventSeries;
use App\Filament\Admin\Resources\EventSeries\Pages\ListEventSeries;
use App\Filament\Admin\Resources\EventSeries\Schemas\EventSeriesForm;
use App\Filament\Admin\Resources\EventSeries\Tables\EventSeriesTable;
use App\Filament\Admin\Resources\Resource;
use App\Models\EventSeries;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class EventSeriesResource extends Resource
{
    protected static ?string $model = EventSeries::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return EventSeriesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return EventSeriesTable::configure($table);
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
            'index' => ListEventSeries::route('/'),
            'create' => CreateEventSeries::route('/create'),
            'edit' => EditEventSeries::route('/{record}/edit'),
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
