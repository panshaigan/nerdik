<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\Users\Tables;

use App\Filament\Admin\Support\AdminCustomEmailForm;
use App\Filament\Tables\Filters\BelongsToFilter;
use App\Filament\Tables\Filters\CommonFilters;
use App\Models\User;
use App\Services\AdminEmail\AdminCustomEmailService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\Column;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use STS\FilamentImpersonate\Actions\Impersonate;

final class UserRecipientTable
{
    /**
     * @return list<Column>
     */
    public static function columns(): array
    {
        return [
            TextColumn::make('name')
                ->searchable(),
            TextColumn::make('nickname')
                ->searchable(),
            TextColumn::make('email')
                ->label('Email address')
                ->searchable(),
            TextColumn::make('sent_emails_count')
                ->counts('sentEmails')
                ->label('Sent emails')
                ->sortable(),
            TextColumn::make('organization.name')
                ->searchable(),
            IconColumn::make('is_admin')
                ->boolean(),
            IconColumn::make('is_event_organizer')
                ->boolean(),
            TextColumn::make('email_verified_at')
                ->dateTime()
                ->sortable(),
            TextColumn::make('created_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
            TextColumn::make('updated_at')
                ->dateTime()
                ->sortable()
                ->toggleable(isToggledHiddenByDefault: true),
        ];
    }

    public static function configureForUserResource(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->filters([
                TernaryFilter::make('is_event_organizer'),
                TernaryFilter::make('is_admin'),
                BelongsToFilter::make('organization_id', 'organization'),
                ...CommonFilters::auditTimestampFilters(includeDeletedAt: false),
            ])
            ->recordActions([
                Impersonate::make()
                    ->redirectTo(route('dashboard')),
                EditAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    self::sendCustomEmailBulkAction(),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function sendCustomEmailBulkAction(): BulkAction
    {
        return BulkAction::make('sendCustomEmail')
            ->label(__('admin_custom_email.actions.send'))
            ->icon(Heroicon::OutlinedPaperAirplane)
            ->schema(AdminCustomEmailForm::components())
            ->requiresConfirmation()
            ->modalHeading(__('admin_custom_email.confirm.send_heading'))
            ->action(function (Collection $records, array $data, AdminCustomEmailService $adminEmail): void {
                $admin = Auth::user();

                if (! $admin instanceof User) {
                    return;
                }

                try {
                    $result = $adminEmail->dispatchToRecipients(
                        $admin,
                        (string) ($data['subject'] ?? ''),
                        (string) ($data['bodyHtml'] ?? ''),
                        $records->modelKeys(),
                    );
                } catch (ValidationException $exception) {
                    Notification::make()
                        ->danger()
                        ->title((string) collect($exception->errors())->flatten()->first())
                        ->send();

                    return;
                }

                Notification::make()
                    ->success()
                    ->title(__('admin_custom_email.notifications.sent_title'))
                    ->body(__('admin_custom_email.notifications.sent_summary', [
                        'queued' => $result->queued,
                        'skipped' => $result->skippedIneligible,
                        'over_limit' => $result->skippedOverLimit,
                    ]))
                    ->send();
            });
    }

    public static function configureForEmailRecipients(Table $table): Table
    {
        return $table
            ->columns(self::columns())
            ->defaultSort('name')
            ->filters([
                Filter::make('verified_only')
                    ->label(__('admin_custom_email.filters.verified_only'))
                    ->query(fn (Builder $query): Builder => $query->whereNotNull('email_verified_at'))
                    ->toggle(),
                Filter::make('exclude_admins')
                    ->label(__('admin_custom_email.filters.exclude_admins'))
                    ->query(fn (Builder $query): Builder => $query->where('is_admin', false))
                    ->toggle(),
                BelongsToFilter::make('organization_id', 'organization'),
            ])
            ->selectCurrentPageOnly(false)
            ->selectable()
            ->checkIfRecordIsSelectableUsing(
                fn ($record): bool => app(AdminCustomEmailService::class)->isEligibleRecipient($record),
            );
    }
}
