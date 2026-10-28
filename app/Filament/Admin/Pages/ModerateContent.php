<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Enums\AvatarSource;
use App\Filament\Admin\Resources\Activities\ActivityResource;
use App\Filament\Admin\Resources\Events\EventResource;
use App\Filament\Admin\Resources\Users\UserResource;
use App\Models\Activity;
use App\Models\Event;
use App\Models\User;
use App\Services\ContentModerationService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Resources\Concerns\HasTabs;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Url;

class ModerateContent extends Page implements HasTable
{
    use HasTabs;
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldExclamation;

    protected static ?string $navigationLabel = 'Content moderation';

    protected static ?string $title = 'Content moderation';

    protected static ?int $navigationSort = 21;

    protected string $view = 'filament-panels::pages.page';

    #[Url(as: 'tab')]
    public ?string $activeTab = null;

    public function mount(): void
    {
        $this->mountCanAuthorizeAccess();
        $this->loadDefaultActiveTab();
    }

    public function updatedActiveTab(): void
    {
        $this->resetPage();

        $this->cachedDefaultTableColumnState = null;

        $this->applyTableColumnManager();

        $this->resetTable();
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_admin;
    }

    public function getTabs(): array
    {
        return [
            'profiles' => Tab::make('Profiles'),
            'activities' => Tab::make('Activities'),
            'events' => Tab::make('Events'),
        ];
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                $this->getTabsContentComponent(),
                EmbeddedTable::make(),
            ]);
    }

    public function table(Table $table): Table
    {
        return match ($this->resolveModerationTab()) {
            'activities' => $this->configureActivitiesTable($table),
            'events' => $this->configureEventsTable($table),
            default => $this->configureProfilesTable($table),
        };
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn (): Builder => $this->getTableQuery());
    }

    protected function getTableQuery(): Builder
    {
        return match ($this->resolveModerationTab()) {
            'activities' => Activity::query()
                ->with(Activity::listingCardEagerLoad())
                ->whereNull('deleted_at'),
            'events' => Event::query()
                ->with(['listingMedia', 'galleryMedia', 'creator', 'media'])
                ->whereNull('deleted_at'),
            default => User::query()
                ->with('profile')
                ->where('is_deleted', false),
        };
    }

    protected function resolveModerationTab(): string
    {
        $tab = $this->activeTab ?? array_key_first($this->getCachedTabs());

        return is_string($tab) && $tab !== '' ? $tab : 'profiles';
    }

    private function configureProfilesTable(Table $table): Table
    {
        $moderation = app(ContentModerationService::class);

        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('preview_image')
                    ->label('Image')
                    ->state(fn (User $record): ?string => $moderation->profilePreviewImageUrl($record))
                    ->square()
                    ->imageHeight(48),
                TextColumn::make('nickname')
                    ->label('Nickname')
                    ->searchable(),
                TextColumn::make('name')
                    ->searchable()
                    ->toggleable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('user_chosen_image')
                    ->label('User-chosen image')
                    ->query(fn (Builder $query): Builder => $query->where(function (Builder $inner): void {
                        $inner->whereHas('profile', fn (Builder $profile): Builder => $profile
                            ->whereNotNull('avatar_source')
                            ->where('avatar_source', '!=', AvatarSource::Generated->value))
                            ->orWhereHas('media', fn (Builder $media): Builder => $media
                                ->where('collection_name', 'avatar'));
                    }))
                    ->toggle(),
            ])
            ->recordActions([
                $this->removeProfilePictureAction(),
                $this->replaceProfilePlaceholderAction(),
                $this->editProfileTextAction(),
                EditAction::make()
                    ->url(fn (User $record): string => UserResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    private function configureActivitiesTable(Table $table): Table
    {
        $moderation = app(ContentModerationService::class);

        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('preview_image')
                    ->label('Image')
                    ->state(fn (Activity $record): ?string => $moderation->activityPreviewImageUrl($record))
                    ->square()
                    ->imageHeight(48),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('description')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('creator.nickname')
                    ->label('Author')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('user_chosen_image')
                    ->label('User-chosen image')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('logo_source'))
                    ->toggle(),
            ])
            ->recordActions([
                $this->removeActivityPictureAction(),
                $this->replaceActivityPlaceholderAction(),
                $this->editActivityTextAction(),
                Action::make('viewPublic')
                    ->label('View public')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Activity $record): string => route('activities.show', $record), shouldOpenInNewTab: true),
                EditAction::make()
                    ->url(fn (Activity $record): string => ActivityResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    private function configureEventsTable(Table $table): Table
    {
        $moderation = app(ContentModerationService::class);

        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('preview_image')
                    ->label('Image')
                    ->state(fn (Event $record): ?string => $moderation->eventPreviewImageUrl($record))
                    ->square()
                    ->imageHeight(48),
                TextColumn::make('name')
                    ->searchable(),
                TextColumn::make('description')
                    ->limit(80)
                    ->searchable(),
                TextColumn::make('creator.nickname')
                    ->label('Author')
                    ->searchable(),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Filter::make('user_chosen_image')
                    ->label('User-chosen image')
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('logo_source'))
                    ->toggle(),
            ])
            ->recordActions([
                $this->removeEventPictureAction(),
                $this->replaceEventPlaceholderAction(),
                $this->editEventTextAction(),
                Action::make('viewPublic')
                    ->label('View public')
                    ->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                    ->url(fn (Event $record): string => route('events.show', $record), shouldOpenInNewTab: true),
                EditAction::make()
                    ->url(fn (Event $record): string => EventResource::getUrl('edit', ['record' => $record])),
            ]);
    }

    private function removeProfilePictureAction(): Action
    {
        return Action::make('removePicture')
            ->label('Remove picture')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (User $record): bool => app(ContentModerationService::class)->hasUserChosenProfileImage($record))
            ->action(function (User $record): void {
                app(ContentModerationService::class)->removeProfilePicture($record);

                Notification::make()
                    ->title('Profile picture removed')
                    ->success()
                    ->send();
            });
    }

    private function removeActivityPictureAction(): Action
    {
        return Action::make('removePicture')
            ->label('Remove picture')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Activity $record): bool => app(ContentModerationService::class)->hasUserChosenActivityImage($record))
            ->action(function (Activity $record): void {
                app(ContentModerationService::class)->removeActivityPicture($record);

                Notification::make()
                    ->title('Activity picture removed')
                    ->success()
                    ->send();
            });
    }

    private function removeEventPictureAction(): Action
    {
        return Action::make('removePicture')
            ->label('Remove picture')
            ->icon(Heroicon::OutlinedPhoto)
            ->color('danger')
            ->requiresConfirmation()
            ->visible(fn (Event $record): bool => app(ContentModerationService::class)->hasUserChosenEventImage($record))
            ->action(function (Event $record): void {
                app(ContentModerationService::class)->removeEventPicture($record);

                Notification::make()
                    ->title('Event picture removed')
                    ->success()
                    ->send();
            });
    }

    private function replaceProfilePlaceholderAction(): Action
    {
        return Action::make('replacePlaceholder')
            ->label('Replace with placeholder')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (User $record): void {
                app(ContentModerationService::class)->applyPlaceholderToProfile($record);

                Notification::make()
                    ->title('Nickname replaced')
                    ->success()
                    ->send();
            });
    }

    private function replaceActivityPlaceholderAction(): Action
    {
        return Action::make('replacePlaceholder')
            ->label('Replace with placeholder')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (Activity $record): void {
                app(ContentModerationService::class)->applyPlaceholderToActivity($record);

                Notification::make()
                    ->title('Activity text replaced')
                    ->success()
                    ->send();
            });
    }

    private function replaceEventPlaceholderAction(): Action
    {
        return Action::make('replacePlaceholder')
            ->label('Replace with placeholder')
            ->icon(Heroicon::OutlinedNoSymbol)
            ->color('warning')
            ->requiresConfirmation()
            ->action(function (Event $record): void {
                app(ContentModerationService::class)->applyPlaceholderToEvent($record);

                Notification::make()
                    ->title('Event text replaced')
                    ->success()
                    ->send();
            });
    }

    private function editProfileTextAction(): Action
    {
        return Action::make('editText')
            ->label('Edit text')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([
                TextInput::make('nickname')
                    ->required()
                    ->maxLength(255),
            ])
            ->fillForm(fn (User $record): array => [
                'nickname' => $record->nickname,
            ])
            ->action(function (User $record, array $data): void {
                app(ContentModerationService::class)->updateProfileNickname($record, (string) $data['nickname']);

                Notification::make()
                    ->title('Nickname updated')
                    ->success()
                    ->send();
            });
    }

    private function editActivityTextAction(): Action
    {
        return Action::make('editText')
            ->label('Edit text')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(4),
            ])
            ->fillForm(fn (Activity $record): array => [
                'name' => $record->name,
                'description' => $record->description,
            ])
            ->action(function (Activity $record, array $data): void {
                app(ContentModerationService::class)->updateActivityText(
                    $record,
                    (string) $data['name'],
                    isset($data['description']) ? (string) $data['description'] : null,
                );

                Notification::make()
                    ->title('Activity text updated')
                    ->success()
                    ->send();
            });
    }

    private function editEventTextAction(): Action
    {
        return Action::make('editText')
            ->label('Edit text')
            ->icon(Heroicon::OutlinedPencilSquare)
            ->schema([
                TextInput::make('name')
                    ->required()
                    ->maxLength(255),
                Textarea::make('description')
                    ->rows(4),
            ])
            ->fillForm(fn (Event $record): array => [
                'name' => $record->name,
                'description' => $record->description,
            ])
            ->action(function (Event $record, array $data): void {
                app(ContentModerationService::class)->updateEventText(
                    $record,
                    (string) $data['name'],
                    isset($data['description']) ? (string) $data['description'] : null,
                );

                Notification::make()
                    ->title('Event text updated')
                    ->success()
                    ->send();
            });
    }
}
