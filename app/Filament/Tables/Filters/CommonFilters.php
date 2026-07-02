<?php

namespace App\Filament\Tables\Filters;

use Carbon\Carbon;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\Indicator;
use Filament\Tables\Filters\SelectFilter;
use Illuminate\Database\Eloquent\Builder;

final class CommonFilters
{
    public static function dateRange(string $column, ?string $label = null): Filter
    {
        $fromField = "{$column}_from";
        $untilField = "{$column}_until";
        $columnLabel = $label ?? str($column)->replace('_', ' ')->headline()->toString();

        return Filter::make($column)
            ->label($columnLabel)
            ->schema([
                DatePicker::make($fromField)
                    ->label("{$columnLabel} from"),
                DatePicker::make($untilField)
                    ->label("{$columnLabel} until"),
            ])
            ->query(function (Builder $query, array $data) use ($column, $fromField, $untilField): Builder {
                return $query
                    ->when(
                        filled($data[$fromField] ?? null),
                        fn (Builder $builder): Builder => $builder->whereDate($column, '>=', $data[$fromField]),
                    )
                    ->when(
                        filled($data[$untilField] ?? null),
                        fn (Builder $builder): Builder => $builder->whereDate($column, '<=', $data[$untilField]),
                    );
            })
            ->indicateUsing(function (array $data) use ($columnLabel, $fromField, $untilField): array {
                $indicators = [];

                if (filled($data[$fromField] ?? null)) {
                    $indicators[] = Indicator::make("{$columnLabel} from ".Carbon::parse($data[$fromField])->toFormattedDateString())
                        ->removeField($fromField);
                }

                if (filled($data[$untilField] ?? null)) {
                    $indicators[] = Indicator::make("{$columnLabel} until ".Carbon::parse($data[$untilField])->toFormattedDateString())
                        ->removeField($untilField);
                }

                return $indicators;
            });
    }

    /**
     * @return list<SelectFilter>
     */
    public static function auditUserFilters(bool $includeDeletedBy = true): array
    {
        $filters = [
            BelongsToFilter::user('created_by'),
            BelongsToFilter::user('updated_by'),
        ];

        if ($includeDeletedBy) {
            $filters[] = BelongsToFilter::user('deleted_by');
        }

        return $filters;
    }

    /**
     * @return list<Filter>
     */
    public static function auditTimestampFilters(bool $includeDeletedAt = true): array
    {
        $filters = [
            self::dateRange('created_at'),
            self::dateRange('updated_at'),
        ];

        if ($includeDeletedAt) {
            $filters[] = self::dateRange('deleted_at');
        }

        return $filters;
    }
}
