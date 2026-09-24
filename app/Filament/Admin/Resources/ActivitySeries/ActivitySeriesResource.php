<?php

namespace App\Filament\Admin\Resources\ActivitySeries;

use App\Filament\Admin\Resources\ActivitySeries\Pages\CreateActivitySeries;
use App\Filament\Admin\Resources\ActivitySeries\Pages\EditActivitySeries;
use App\Filament\Admin\Resources\ActivitySeries\Pages\ListActivitySeries;
use App\Filament\Admin\Resources\ActivitySeries\Schemas\ActivitySeriesForm;
use App\Filament\Admin\Resources\ActivitySeries\Tables\ActivitySeriesTable;
use App\Filament\Admin\Resources\Resource;
use App\Models\ActivitySeries;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ActivitySeriesResource extends Resource
{
    protected static ?string $model = ActivitySeries::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return ActivitySeriesForm::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return ActivitySeriesTable::configure($table);
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
            'index' => ListActivitySeries::route('/'),
            'create' => CreateActivitySeries::route('/create'),
            'edit' => EditActivitySeries::route('/{record}/edit'),
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
