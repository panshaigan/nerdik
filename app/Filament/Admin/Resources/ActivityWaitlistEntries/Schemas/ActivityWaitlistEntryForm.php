<?php

namespace App\Filament\Admin\Resources\ActivityWaitlistEntries\Schemas;

use App\Filament\Forms\Components\BelongsToSelect;
use App\Services\ActivityFamiliarityService;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class ActivityWaitlistEntryForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                BelongsToSelect::activity()
                    ->required(),
                BelongsToSelect::user('user_id')
                    ->required(),
                TextInput::make('position')
                    ->numeric(),
                Placeholder::make('familiarity_summary')
                    ->label('Familiarity')
                    ->content(fn ($record): string => app(ActivityFamiliarityService::class)
                        ->formatSnapshotSummary(is_array($record?->familiarity) ? $record->familiarity : null)
                        ?: '—'),
            ]);
    }
}
