<?php

namespace App\Support\Filament;

use Filament\Forms\Components\DateTimePicker;
use Filament\Schemas\Schema;
use Filament\Support\Facades\FilamentTimezone;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;

final class ConfigureFilamentDisplay
{
    public static function register(): void
    {
        FilamentTimezone::set(fn (): string => display_timezone());

        Table::configureUsing(function (Table $table): void {
            $table
                ->defaultPaginationPageOption(50)
                ->defaultDateTimeDisplayFormat(fn (): string => apply_display_time_format_to_php('M j, Y H:i:s'))
                ->defaultTimeDisplayFormat(fn (): string => apply_display_time_format_to_php('H:i:s'));
        });

        Schema::configureUsing(function (Schema $schema): void {
            $schema
                ->defaultDateTimeDisplayFormat(fn (): string => apply_display_time_format_to_php('M j, Y H:i:s'))
                ->defaultTimeDisplayFormat(fn (): string => apply_display_time_format_to_php('H:i:s'));
        });

        DateTimePicker::configureUsing(function (DateTimePicker $picker): void {
            $picker
                ->defaultDateTimeDisplayFormat(fn (): string => apply_display_time_format_to_php('M j, Y H:i'))
                ->defaultDateTimeWithSecondsDisplayFormat(fn (): string => apply_display_time_format_to_php('M j, Y H:i:s'))
                ->defaultTimeDisplayFormat(fn (): string => apply_display_time_format_to_php('H:i'))
                ->locale(fn (): string => app()->getLocale());
        });
    }

    public static function normalizeSelectFilterAttributes(Table $table): void
    {
        $modelClass = $table->getModel();

        if ($modelClass === null) {
            return;
        }

        /** @var Model $model */
        $model = app($modelClass);

        foreach ($table->getFilters(withHidden: true) as $filter) {
            if (! $filter instanceof SelectFilter) {
                continue;
            }

            if ($filter->queriesRelationships()) {
                continue;
            }

            if ($filter->getAttribute() !== $filter->getName()) {
                continue;
            }

            $resolvedAttribute = FilamentFilterAttributeResolver::resolveBelongsToForeignKey(
                $model,
                $filter->getName(),
            );

            if ($resolvedAttribute === null) {
                continue;
            }

            $filter->attribute($resolvedAttribute);
        }
    }
}
