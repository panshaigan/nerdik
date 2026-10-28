<?php

declare(strict_types=1);

namespace App\Filament\Admin\Pages;

use App\Filament\Admin\Resources\Users\Tables\UserRecipientTable;
use App\Filament\Admin\Support\AdminCustomEmailForm;
use App\Models\User;
use App\Services\AdminEmail\AdminCustomEmailService;
use App\Services\AdminEmail\AdminEmailDispatchResult;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class SendCustomEmail extends Page implements HasTable
{
    use InteractsWithTable {
        makeTable as makeBaseTable;
    }

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedPaperAirplane;

    protected static ?string $navigationLabel = null;

    protected static ?string $title = null;

    protected static ?int $navigationSort = 12;

    protected string $view = 'filament-panels::pages.page';

    /** @var array{subject?: string, bodyHtml?: string} */
    public array $composeData = [
        'subject' => '',
        'bodyHtml' => '',
    ];

    public static function getNavigationLabel(): string
    {
        return __('admin_custom_email.navigation');
    }

    public function getTitle(): string
    {
        return __('admin_custom_email.navigation');
    }

    public function mount(): void
    {
        $this->mountCanAuthorizeAccess();

        $this->tableFilters = [
            'verified_only' => ['isActive' => true],
            'exclude_admins' => ['isActive' => true],
        ];
    }

    public static function canAccess(): bool
    {
        $user = auth()->user();

        return $user instanceof User && $user->is_admin;
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make(AdminCustomEmailForm::components())
                    ->statePath('composeData')
                    ->columns(1),
                EmbeddedTable::make(),
            ]);
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('sendTest')
                ->label(__('admin_custom_email.actions.send_test'))
                ->icon(Heroicon::OutlinedBeaker)
                ->action(function (AdminCustomEmailService $adminEmail): void {
                    $admin = Auth::user();

                    if (! $admin instanceof User) {
                        return;
                    }

                    try {
                        $adminEmail->dispatchTestToSender(
                            $admin,
                            (string) ($this->composeData['subject'] ?? ''),
                            (string) ($this->composeData['bodyHtml'] ?? ''),
                        );
                    } catch (ValidationException $exception) {
                        $this->sendValidationNotification($exception);

                        return;
                    }

                    Notification::make()
                        ->success()
                        ->title(__('admin_custom_email.notifications.test_queued'))
                        ->send();
                }),
        ];
    }

    public function table(Table $table): Table
    {
        return UserRecipientTable::configureForEmailRecipients($table)
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('send')
                        ->label(__('admin_custom_email.actions.send'))
                        ->icon(Heroicon::OutlinedPaperAirplane)
                        ->requiresConfirmation()
                        ->modalHeading(__('admin_custom_email.confirm.send_heading'))
                        ->modalDescription(fn (): string => __('admin_custom_email.confirm.send_body', [
                            'count' => $this->getSelectedTableRecords(shouldFetchSelectedRecords: false)->count(),
                        ]))
                        ->action(function (Collection $records, AdminCustomEmailService $adminEmail): void {
                            $admin = Auth::user();

                            if (! $admin instanceof User) {
                                return;
                            }

                            try {
                                $result = $adminEmail->dispatchToRecipients(
                                    $admin,
                                    (string) ($this->composeData['subject'] ?? ''),
                                    (string) ($this->composeData['bodyHtml'] ?? ''),
                                    $records->modelKeys(),
                                );
                            } catch (ValidationException $exception) {
                                $this->sendValidationNotification($exception);

                                return;
                            }

                            $this->sendDispatchNotification($result);
                            $this->deselectAllTableRecords();
                        }),
                ]),
            ]);
    }

    protected function makeTable(): Table
    {
        return $this->makeBaseTable()
            ->query(fn (): Builder => User::query());
    }

    private function sendValidationNotification(ValidationException $exception): void
    {
        Notification::make()
            ->danger()
            ->title((string) collect($exception->errors())->flatten()->first())
            ->send();
    }

    private function sendDispatchNotification(AdminEmailDispatchResult $result): void
    {
        $body = __('admin_custom_email.notifications.sent_summary', [
            'queued' => $result->queued,
            'skipped' => $result->skippedIneligible,
            'over_limit' => $result->skippedOverLimit,
        ]);

        Notification::make()
            ->success()
            ->title(__('admin_custom_email.notifications.sent_title'))
            ->body($body)
            ->send();
    }
}
