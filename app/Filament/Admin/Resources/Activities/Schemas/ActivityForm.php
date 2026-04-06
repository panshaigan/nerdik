<?php

namespace App\Filament\Admin\Resources\Activities\Schemas;

use App\Enums\ActivityStatus;
use App\Enums\ActivityType;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Schemas\Schema;

class ActivityForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')
                    ->required(),
                Textarea::make('desc')
                    ->rows(4)
                    ->columnSpanFull(),
                Select::make('type')
                    ->options(collect(ActivityType::cases())->mapWithKeys(fn (ActivityType $t) => [$t->value => $t->value]))
                    ->required(),
                TextInput::make('min_participants')
                    ->numeric()
                    ->default(null),
                TextInput::make('max_participants')
                    ->numeric()
                    ->default(null),
                TextInput::make('minimum_age')
                    ->numeric()
                    ->default(null),
                TextInput::make('price')
                    ->numeric()
                    ->default(null)
                    ->prefix('$'),
                Toggle::make('is_host_passive')
                    ->default(false),
                TextInput::make('created_by')
                    ->numeric()
                    ->default(null),
                Toggle::make('requires_approval')
                    ->required(),
                TextInput::make('cancellation_deadline_in_hours')
                    ->numeric()
                    ->default(null),
                Select::make('status')
                    ->options(collect(ActivityStatus::cases())->mapWithKeys(fn (ActivityStatus $s) => [$s->value => $s->value]))
                    ->required()
                    ->default(ActivityStatus::Planned->value),
                TextInput::make('logo_path')
                    ->default(null),
                TextInput::make('duration_in_minutes')
                    ->numeric()
                    ->default(null),
                Toggle::make('allows_observers')
                    ->required(),
                TextInput::make('deleted_by')
                    ->numeric()
                    ->default(null),
                TextInput::make('updated_by')
                    ->numeric()
                    ->default(null),
            ]);
    }
}
