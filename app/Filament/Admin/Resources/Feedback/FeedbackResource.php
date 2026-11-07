<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Feedback;

use App\Filament\Admin\Enums\AdminNavigationGroup;
use App\Filament\Admin\Resources\Feedback\Pages\ListFeedback;
use App\Filament\Admin\Resources\Feedback\Pages\ViewFeedback;
use App\Filament\Admin\Resources\Feedback\Schemas\FeedbackInfolist;
use App\Filament\Admin\Resources\Feedback\Tables\FeedbackTable;
use App\Filament\Admin\Resources\Resource;
use App\Models\Feedback as FeedbackModel;
use BackedEnum;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class FeedbackResource extends Resource
{
    protected static ?string $model = FeedbackModel::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedChatBubbleLeftRight;

    protected static string|UnitEnum|null $navigationGroup = AdminNavigationGroup::Communication;

    protected static ?int $navigationSort = 3;

    protected static ?string $recordTitleAttribute = 'subject';

    protected static ?string $navigationLabel = 'Feedback';

    protected static ?string $modelLabel = 'feedback';

    protected static ?string $pluralModelLabel = 'feedback';

    public static function infolist(Schema $schema): Schema
    {
        return FeedbackInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return FeedbackTable::configure($table);
    }

    public static function getPages(): array
    {
        return [
            'index' => ListFeedback::route('/'),
            'view' => ViewFeedback::route('/{record}'),
        ];
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function canDeleteAny(): bool
    {
        return false;
    }
}
